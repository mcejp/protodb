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

use candb\model\DrcIncident;
use candb\model\Message;
use candb\model\MessageField;
use candb\model\Package;
use candb\model\Unit;
use candb\service\BusService;
use candb\service\drc\DrcService;
use candb\service\exception\EntityNotFoundException;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;
use candb\ui\BusListField;
use candb\ui\CanIdField;
use candb\ui\EnumField;
use candb\ui\Form;
use candb\ui\IntegerField;
use candb\ui\TableField;
use candb\ui\TextField;
use candb\ui\TextFieldWithExplicitNA;

final class MessageController extends BaseController
{
    private $drc_service, $messages, $packages, $units;

    public function __construct(DrcService $drc_service,
                                private BusService $bus_service,
                                MessagesService $messages, PackagesService $packages, UnitsService $units)
    {
        parent::__construct();
        $this->drc_service = $drc_service;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    private function build_message_form(bool $edit, Unit $node, array $enum_types): Form
    {
        $form = new \candb\ui\Form("message", $edit);
        $form->add_field(new TextField("name"));
        $form->add_field(new CanIdField("can_id"));
        $form->add_field($desc = new TextField("description", TextField::TYPE_TEXTAREA));
        $desc->use_markdown();
        $form->add_field(new TextField("tx_period", TextField::TYPE_INTEGER, 'ms'));
        $form->add_field(new TextField("timeout", TextField::TYPE_INTEGER, 'ms'));

        $form->add_field(EnumField::with_option_groups("bus_id", $this->get_bus_option_groups($node, allow_null: true)));

        $form->add_field(new BusListField("buses", $this->get_bus_option_groups($node, allow_null: false)));

        $form->getFieldByName('name')->makeRequired();
        $form->getFieldByName('tx_period')->set_empty_value_is_null(true);
        $form->getFieldByName('tx_period')->set_min_value(1);
        $form->getFieldByName('timeout')->set_empty_value_is_null(true);
        $form->getFieldByName('timeout')->set_min_value(1);

        $type_options = [
            ['uint', 'uint'],
            ['int', 'int'],
            ['bool', 'bool'],
            ['float', 'float'],
            ['multiplex', 'multiplex'],
            [MessageField::TYPE_RESERVED, 'reserved']
        ];

        foreach ($enum_types as $id => $enum) {
            if ($edit)
                $value = $enum->name;
            else
                $value = '<a href="'.htmlentities(\candb\model\EnumType::s_url($enum->id), ENT_QUOTES).'">' . $enum->name . '</a>';

            $type_options[] = [(string)$enum->id, $value];
        }

        $array_enum = ['1' => '–'];
        for ($i = 2; $i <= Message::CAN_MAX_ARRAY_SIZE; $i++) {
            $array_enum[$i] = "[$i]";
        }

        $form->add_field(new TableField("fields", [
            ['name' => 'type', 'title' => 'Type', 'field' => EnumField::with_option_groups(null, ['' => $type_options])],
            ['name' => 'bit_size', 'title' => 'Size in bits', 'field' => new IntegerField(null, null, true, 1)],
            ['name' => 'name', 'title' => 'Name', 'field' => new TextField()],
            ['name' => 'description', 'title' => 'Description', 'field' => ($field_desc = new TextField())],
            ['name' => 'unit', 'title' => 'Unit',
                'field' => (new TextFieldWithExplicitNA())->set_max_length(20), 'popover_id' => 'message_field_unit'],
            ['name' => 'factor', 'title' => 'Step',
                'field' => (new TextFieldWithExplicitNA())->set_max_length(20), 'popover_id' => 'message_field_factor'],
            ['name' => 'offset', 'title' => 'Offset',
                'field' => (new TextFieldWithExplicitNA())->set_max_length(20), 'popover_id' => 'message_field_offset'],
            ['name' => 'min', 'title' => 'Min',
                'field' => (new TextFieldWithExplicitNA())->set_max_length(20), 'popover_id' => 'message_field_min'],
            ['name' => 'max', 'title' => 'Max',
                'field' => (new TextFieldWithExplicitNA())->set_max_length(20), 'popover_id' => 'message_field_max'],
            ['name' => 'array_length', 'title' => 'Array', 'field' => new EnumField(null, $array_enum)],
        ], true, 'MessageFieldsFormTable'));
        $field_desc->use_markdown();

        //$form->getFieldByName("fields")->getColumnByName("type")['field']->makeRequired();    FIXME
        //$form->getFieldByName("fields")->getColumnByName("array_size")['field']->makeRequired();
        $form->getFieldByName("fields")->get_column_by_name("offset")['field']->set_max_length(20);

        return $form;
    }

    private function get_bus_option_groups(Unit $node, bool $allow_null) {
        $my_buses = [];

        if ($allow_null) {
            $my_buses[] = [null, '—'];
        }

        foreach ($node->bus_links as $bus_link) {
            $my_buses[] = [$bus_link->bus_id, $bus_link->bus_name];
        }

        $buses = [('Associated to '.$node->name) => $my_buses];

        $all_packages = $this->packages->get_all(with_buses: true);
        usort($all_packages, fn(Package $p1, Package $p2) => $p1->name <=> $p2->name);

        foreach ($all_packages as $package) {
            $group = array_map(fn($bus) => [$bus->id, $package->name.' / '.$bus->name], $package->buses);
            $buses[$package->name] = $group;
        }

        return $buses;
    }

    /**
     * @throws \ReflectionException
     */
    public function handle_delete(int $message_id): HttpResult
    {
        if (!$this->user_is_admin()) {
            return HttpResult::with_response_code(HttpResult::FORBIDDEN_CODE);
        }

        try {
            $message = $this->messages->byId($message_id, with_buses: false, with_fields: false);
        }
        catch (EntityNotFoundException $exception) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        $unit_id = $message->node_id;
        $this->messages->delete($message, $this->getCurrentUsername());

        return HttpResult::make_redirect(Unit::s_url($unit_id));
    }

    /**
     * @return array|HttpResult
     * @throws \candb\service\exception\EntityNotFoundException
     * @throws \ReflectionException
     */
    public function handle_index(int $unit_id = 0, int $message_id = 0, int $edit = 0)
    {
        if ($message_id) {
            try {
                $message = $this->messages->byId($message_id, with_buses: true, with_fields: true);
            }
            catch (EntityNotFoundException $exception) {
                return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
            }

            // For presentation, NULL values are transformed to "?".
            // Empty values, on the other hand, are interpreted as "N/A" (= property not applicable to this signal)
            foreach ($message->fields as &$field) {
                $field->unit = ($field->unit !== null) ? $field->unit : '?';
                $field->factor = ($field->factor !== null) ? $field->factor : '?';
                $field->offset = ($field->offset !== null) ? $field->offset : '?';
                $field->min = ($field->min !== null) ? $field->min : '?';
                $field->max = ($field->max !== null) ? $field->max : '?';
            }
        }
        else {
            $message = new Message(null, $unit_id, null, 0, 'UNDEF', '', '',
                                   buses: []);
            $message->fields = [];

            $edit = 1;
        }

        $unit = $this->units->byId($message->node_id, true, false, true);
        $pkg = $this->packages->byId($unit->package_id);

        // Ensure that Associated bus is a permissible one, otherwise sanitize
        if ($message->bus_id !== null) {
            if (!in_array($message->bus_id, $unit->get_bus_ids())) {
                $this->add_message('Data error: invalid bus ID (' . $message->bus_id . ')', 'warning');
                $message->bus_id = null;
            }
        }

        $form = $this->build_message_form(!!$edit, $unit, $unit->enum_types);

        $form_data = $form->get_submitted_data();

        if ($form_data) {
            $message->bus_id = $form_data['bus_id'];
            $message->set_can_id($form_data['can_id_type'], $form_data['can_id']);
            $message->name = trim($form_data['name']);
            $message->description = $form_data['description'];
            $message->tx_period = $form_data['tx_period'];
            $message->timeout = $form_data['timeout'];

            $message->set_buses($form_data['buses']);

            $fields = [];

            foreach ($form_data['fields'] as $field) {
                $id = array_key_exists('id', $field) ? $field['id'] : null;

                $fields[] = new MessageField($id,
                                             null,
                                             count($fields),
                                             trim($field['name']),
                                             trim($field['description']),
                                             $field['type'],
                                             $field['bit_size'],
                                             $field['array_length'],
                                             ($field['unit'] !== '?') ? $field['unit'] : null,
                                             ($field['factor'] !== '?') ? $field['factor'] : null,
                                             ($field['offset'] !== '?') ? $field['offset'] : null,
                                             ($field['min'] !== '?') ? $field['min'] : null,
                                             ($field['max'] !== '?') ? $field['max'] : null
                                             );
            }

            if ($message->id) {
                $this->messages->update($message, $this->getCurrentUsername());
            }
            else {
                $message->id = $this->messages->insert($message, $this->getCurrentUsername());
            }

            $this->messages->updateFields($message->id, $fields);

            if ($message_id) {
                // Re-run DRC
                $this->drc_service->run_for_unit($unit->id);

                $this->add_message("Save successful.", "success");

                // Reload the entire thing to ensure consistency
                $message = $this->messages->byId($message->id, with_buses: true, with_fields: true);
            }
            else {
                return new HttpResult(['Location' => $message->url()], null);
            }
        }

        if ($message->id) {
            if (!$edit)
                $message->layout();

            foreach ($message->warnings() as $warning)
                $this->add_message('Warning: ' . $warning, "warning");

            // TOOD: nasty service leak
            $sent_by = $this->messages->getAllUnitsWithOperation($message, 'SENDER');
            $received_by = $this->messages->getAllUnitsWithOperation($message, 'RECEIVER');
        }
        else {
            $sent_by = [];
            $received_by = [];
        }

        $form->set_data([
            'bus_id' => $message->bus_id,
            'can_id' => $message->get_can_id(),
            'can_id_type' => $message->get_can_id_type(), // TODO: omitting this doesn't trigger test failure
            'description' => $message->description,
            'fields' => $message->fields,
            'name' => $message->name,
            'timeout' => $message->timeout,
            'tx_period' => $message->tx_period,
            'buses' => $message->get_buses(),
        ]);

        // Get number of DRC incidents by type
        $incident_stats = $this->drc_service->incident_counts_by_message($message_id);

        return [
            'path' => 'views/message',
            'modelpath' => [$pkg, $unit, $message],
            'editing' => $edit,
            'message' => $message,
            'unit' => $unit,
            'unit_id' => $unit_id,
            'pkg' => $pkg,
            'form' => $form,
            'sent_by' => $sent_by,
            'received_by' => $received_by,

            'drc_num_errors' => $incident_stats[DrcIncident::ERROR],
            'drc_num_warnings' => $incident_stats[DrcIncident::WARNING],
        ];
    }
}
