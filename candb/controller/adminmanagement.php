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

use candb\DB;
use candb\model\EnumType;
use candb\service\EnumTypesService;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;
use candb\ui\EnumField;
use candb\ui\TextField;

final class AdminManagement extends BaseController
{
    private $pdo;

    private $enum_types;
    private $packages;
    private $units;
    private $messages;

    public function __construct(DB $db,
                                EnumTypesService $enum_types,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units) {
        parent::__construct();
        $this->pdo = $db->getPdo();
        $this->enum_types = $enum_types;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    // Public to make testable
    public function copy_unit(int $unit_id, int $target_package_id): int
    {
        // This loads buses, messages, enums
        $unit = $this->units->byId($unit_id, true, true, true) or die();

        //var_dump($unit);
        //echo "<br><br>";

        /** @var EnumType[] $enum_types */ $enum_types = [];
        $messages = [];

        foreach ($unit->enum_types as $enum_type) {
            $full_enum_type = $this->enum_types->byId($enum_type->id);
            //var_dump($full_enum_type);
            //echo "<br><br>";

            // unlink from previous entities
            foreach ($full_enum_type->items as &$item) {
                $item->id = NULL;
            }

            $enum_types[] = $full_enum_type;
        }

        foreach ($unit->messages as $message) {
            $full_message = $this->messages->byId($message->id, with_buses: true, with_fields: true);
            //var_dump($full_message);
            //echo "<br><br>";

            $messages[] = $full_message;
        }

        // Insert unit, enum_item, enum_types, messages, message_field
        $new_unit = clone $unit;
        $new_unit->id = null;
        $new_unit->package_id = $target_package_id;

        $unit_id = $this->units->insert($new_unit, $this->getCurrentUsername());
        assert($unit_id !== null);

        $enum_mappings = [];
        $message_mappings = [];             // map of original message ID => copied message ID

        foreach ($enum_types as $enum_type) {
            $enum_type->node_id = $unit_id;
            $enum_type_id = $this->enum_types->insert($enum_type, $enum_type->who_changed);
            $this->enum_types->updateItems($enum_type_id, $enum_type->items);

            $enum_mappings[$enum_type->id] = $enum_type_id;
        }

        foreach ($messages as $message) {
            // unlink from previous entities
            foreach ($message->fields as &$field) {
                $field->id = NULL;
                if (array_key_exists($field->type, $enum_mappings)) {
                    $field->type = $enum_mappings[$field->type];
                }
            }

            // FIXME: fix what?
            $new_message = clone $message;
            $new_message->id = null;
            $new_message->node_id = $unit_id;
            $message_id = $this->messages->insert($new_message, $message->who_changed);
            $this->messages->updateFields($message_id, $message->fields);

            $message_mappings[$message->id] = $message_id;
        }

        $sent_messages = [];
        $received_messages = [];

        foreach ($unit->sent_messages as $sent_message) {
            if (array_key_exists($sent_message['message_id'], $message_mappings)) {
                $sent_messages[] = ['id' => null,
                                    'message_id' => $message_mappings[$sent_message['message_id']]];
            }
        }

        foreach ($unit->received_messages as $received_message) {
            if (array_key_exists($received_message['message_id'], $message_mappings)) {
                $received_messages[] = ['id' => null,
                                        'message_id' => $message_mappings[$received_message['message_id']]];
            }
        }

        $this->units->updateMessageLinks($unit_id, $sent_messages, $received_messages);

        return $unit_id;
    }

    public function handle_index(): array {
        //$package_copy_form = new \candb\Form("package_copy", true);
        //$package_copy_form->add_field(new \candb\EnumField('package_id', $this->packages->all_package_names()));

        $unit_copy_form = new \candb\ui\Form("unit_copy", true);
        $unit_copy_form->add_field(new EnumField('unit_id', $this->units->allUnitNames()));
        $unit_copy_form->add_field(new EnumField('package_id', $this->packages->allPackageNames()));

        $form_data = $unit_copy_form->get_submitted_data();

        if ($form_data) {
            $unit_id = $this->copy_unit((int)$form_data['unit_id'], (int)$form_data['package_id']);
            $this->add_message('New unit id: ' . $unit_id, "success");

            // TODO: must trigger DRC run
        }

        $node_move_form = new \candb\ui\Form("move_node", true);
        $node_move_form->add_field(new EnumField('unit_id', $this->units->allUnitNames()));
        $node_move_form->add_field(new EnumField('package_id', $this->packages->allPackageNames()));

        $form_data = $node_move_form->get_submitted_data();

        if ($form_data) {
            $this->units->move_to_package((int)$form_data['unit_id'], (int)$form_data['package_id'],
                    $this->getCurrentUsername());
            $this->add_message('Ok.', "success");

            // TODO: must trigger DRC run
        }

        return [
            'path' => 'views/admin-management/index',
            'modelpath' => [],
            //'package_copy_form' => $package_copy_form,
            'unit_copy_form' => $unit_copy_form,
            'node_move_form' => $node_move_form,
        ];
    }
}
