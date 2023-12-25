<?php declare(strict_types=1);
/*
 * Copyright (C) 2016-2023 Martin Cejp
 *
 * This file is part of ProtoDB.
 *
 * ProtoDB is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * ProtoDB is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with ProtoDB.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace candb\controller;

use candb\model\EnumItem;
use candb\model\Unit;
use candb\service\EnumTypesService;
use candb\service\exception\EntityNotFoundException;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;
use candb\ui\TableField;
use candb\ui\TextField;

final class EnumTypeController extends BaseController
{
    private $enum_types, $messages, $packages, $units;

    public function __construct(EnumTypesService $enum_types,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units)
    {
        parent::__construct();
        $this->enum_types = $enum_types;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    /**
     * @throws \ReflectionException
     */
    public function handle_delete(int $enum_type_id): HttpResult
    {
        if (!$this->user_is_admin()) {
            return HttpResult::with_response_code(HttpResult::FORBIDDEN_CODE);
        }

        try {
            $enum_type = $this->enum_types->byId($enum_type_id);
        }
        catch (EntityNotFoundException $exception) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        $unit_id = $enum_type->node_id;
        // FIXME: No soft-delete for enum types
        $this->enum_types->delete_forever_by_id($enum_type->id);

        return HttpResult::make_redirect(Unit::s_url($unit_id));
    }

    /**
     * @param int $unit_id
     * @param int $enum_type_id
     * @param int $edit
     * @return array|HttpResult
     * @throws EntityNotFoundException
     * @throws \ReflectionException
     */
    public function handle_index(int $unit_id = 0, int $enum_type_id = 0, int $edit = 0)
    {
        if ($enum_type_id) {
            try {
                $enum_type = $this->enum_types->byId($enum_type_id);
            }
            catch (EntityNotFoundException $exception) {
                return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
            }
        }
        else {
            $enum_type = new \candb\model\EnumType(null, $unit_id, '', '');
            $enum_type->items = [];

            $edit = 1;
        }

        $unit = $this->units->byId($enum_type->node_id, true, true);
        $pkg = $this->packages->byId($unit->package_id);

        $form = new \candb\ui\Form("enum_type", $edit);

        $form->add_field((new TextField("name"))->makeRequired());
        $form->add_field(($desc = new TextField("description", 'textarea'))->makeRequired());
        $desc->use_markdown();
        $form->add_field(new TableField("items", [
            ['name' => 'name', 'title' => 'Name', 'field' => (new TextField())->makeRequired()],
            ['name' => 'description', 'title' => 'Description', 'field' => ($field_desc = new TextField())->makeRequired()],
            ['name' => 'value', 'title' => 'Value', 'field' => (new TextField(null, 'integer'))->makeRequired()],
        ], true));
        $field_desc->use_markdown();

        $form_data = $form->get_submitted_data();

        if ($form_data) {
            $enum_type->name = trim($form_data['name']);
            $enum_type->description = $form_data['description'];

            $items = [];

            foreach ($form_data['items'] as $item) {
                $id = array_key_exists('id', $item) ? $item['id'] : null;

                $items[] = new EnumItem($id, null, count($items), $item['name'], $item['value'], $item['description']);
            }

            if ($enum_type->id)
                $this->enum_types->update($enum_type, $this->getCurrentUsername());
            else
                $enum_type->id = $this->enum_types->insert($enum_type, $this->getCurrentUsername());

            $this->enum_types->updateItems($enum_type->id, $items);

            if ($enum_type_id) {
                $this->add_message("Save successful.", "success");

                // Reload the entire thing to ensure consistency
                $enum_type = $this->enum_types->byId($enum_type->id);
            }
            else {
                return new HttpResult(['Location' => $enum_type->url()], null);
            }
        }

        $form->set_data((array) $enum_type);

        return [
            'path' => 'views/enum_type',
            'modelpath' => [$pkg, $unit, $enum_type],
            'editing' => $edit,
            'pkg' => $pkg,
            'unit' => $unit,
            'enum_type' => $enum_type,
            'form' => $form,
        ];
    }
}
