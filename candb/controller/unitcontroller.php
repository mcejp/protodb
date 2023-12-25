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

use candb\model\BusLink;
use candb\model\DrcIncident;
use candb\model\Unit;
use candb\service\BusService;
use candb\service\drc\DrcService;
use candb\service\exception\EntityNotFoundException;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;
use candb\ui\EnumField;
use candb\ui\MessageListField;
use candb\ui\TableField;
use candb\ui\TextField;

function sanitize($name) {
    return preg_replace('/[^a-zA-Z0-9_]+/', '_', $name);
}

final class UnitController extends BaseController
{
    const DEFAULT_CODE_MODEL_VERSION = 2;

    private $drc_service, $messages, $packages, $units;

    /** @var BusService */
    private $bus_service;

    public function __construct(BusService $bus_service,
                                DrcService $drc_service,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units)
    {
        parent::__construct();
        $this->bus_service = $bus_service;
        $this->drc_service = $drc_service;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    /**
     * @param int $package_id
     * @param int $unit_id
     * @param int $edit
     * @return array|HttpResult
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    public function handle_index(int $package_id = 0, int $unit_id = 0, int $edit = 0)
    {
        if ($unit_id) {
            try {
                $unit = $this->units->byId($unit_id, true, true, true);
            }
            catch (EntityNotFoundException $exception) {
                return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
            }
        }
        else {
            $unit = new Unit(null, $package_id, '', '', null, '', '', self::DEFAULT_CODE_MODEL_VERSION);
            $unit->bus_links = [];
            $unit->sent_messages = [];
            $unit->received_messages = [];

            $edit = 1;
        }

        $pkg = $this->packages->byId($unit->package_id);

        $form = new \candb\ui\Form("unit", $edit);

        $form->add_field(new TextField("name"));
        $form->getFieldByName('name')->makeRequired();

        $form->add_field($desc = new TextField("description", TextField::TYPE_TEXTAREA));
        $desc->use_markdown();
        $form->add_field(new TextField("authors_hw"));
        $form->add_field(new TextField("authors_sw"));
        $form->add_field(new EnumField("code_model_version", $this->get_code_model_versions()));
        $form->add_field(new TableField("bus_links", [
            ['name' => 'bus_id', 'title' => 'Bus', 'field' => new EnumField(NULL, $this->bus_service->all_bus_names())],
            //['name' => 'endianness', 'title' => 'Endianness', 'field' => new EnumField(NULL, ['LE' => 'Little-Endian', 'BE' => 'Big-Endian'])],
            ['name' => 'note', 'title' => 'Note', 'field' => new TextField()],
        ], false));
        $form->add_field(new MessageListField("sent_messages", $this->packages, $pkg->id, $unit->id ?: null));
        $form->add_field(new MessageListField("received_messages", $this->packages, $pkg->id, $unit->id ?: null));

        $form_data = $form->get_submitted_data();

        if ($form_data) {
            // Remove invalid characters from node name
            // TODO: this should be done more systematically and probably elsewhere
            $unit->name = sanitize(trim($form_data['name']));
            $unit->description = $form_data['description'];
            $unit->authors_hw = $form_data['authors_hw'];
            $unit->authors_sw = $form_data['authors_sw'];
            $unit->code_model_version = $form_data['code_model_version'];

            $sent_messages = [];
            $received_messages = [];

            $bus_links = [];

            foreach ($form_data['bus_links'] as $bus_link) {
                $bus_links[] = new BusLink(array_key_exists('id', $bus_link) ? $bus_link['id'] : null,
                                           $bus_link['bus_id'], null, $bus_link['note']);
            }

            foreach ($form_data['sent_messages'] as $message_link) {
                $sent_messages[] = ['id' => array_key_exists('id', $message_link) ? $message_link['id'] : null,
                                    'message_id' => $message_link['message_id']];
            }

            foreach ($form_data['received_messages'] as $message_link) {
                $received_messages[] = ['id' => array_key_exists('id', $message_link) ? $message_link['id'] : null,
                                        'message_id' => $message_link['message_id']];
            }

            if ($unit->id) {
                $this->units->update($unit, $this->getCurrentUsername());
            }
            else {
                $unit->id = $this->units->insert($unit, $this->getCurrentUsername());
            }

            $this->units->updateBusLinks($unit->id, $bus_links);
            $this->units->updateMessageLinks($unit->id, $sent_messages, $received_messages);

            if ($unit_id) {
                $this->add_message("Save successful.", "success");

                // Reload the entire thing to ensure consistency
                $unit = $this->units->byId($unit->id, true, true, true);
            }
            else {
                return new HttpResult(['Location' => $unit->url()], null);
            }
        }

        if ($unit->id) {
            if ($unit->why_locked) {
                $this->add_message('This unit was locked by ' . htmlentities($unit->who_locked, ENT_QUOTES) . ' on ' . $unit->when_locked
                    . '. Lock reason: ' . htmlentities($unit->why_locked, ENT_QUOTES), "warning");
            }
        }

        $sent_messages_by_package = [];
        foreach ($unit->sent_messages as $message) {
            $sent_messages_by_package[$message['message']->package_id][] = $message['message'];
        }
        ksort($sent_messages_by_package);

        $received_messages_by_package = [];
        foreach ($unit->received_messages as $message) {
            $received_messages_by_package[$message['message']->package_id][] = $message['message'];
        }
        ksort($received_messages_by_package);

        $form->set_data((array) $unit);

        // Get number of DRC incidents by type
        $incident_stats = $this->drc_service->incident_counts_by_node($unit_id);

        // Resolve all buses referenced by messages
        $bus_ids = array_unique(array_map(fn($message) => $message->bus_id, $unit->messages));
        $bus_ids = array_filter($bus_ids, fn($id) => ($id !== null));
        $buses = $this->bus_service->get_by_ids($bus_ids);

        return [
            'path' => 'views/unit',
            'modelpath' => [$pkg, $unit],
            'editing' => $edit,
            //'package_id' => $package_id,
            'pkg' => $pkg,
            'unit' => $unit,
            'form' => $form,
            'sent_messages' => $sent_messages_by_package,
            'received_messages' => $received_messages_by_package,
            'buses' => $buses,

            'drc_num_errors' => $incident_stats[DrcIncident::ERROR],
            'drc_num_warnings' => $incident_stats[DrcIncident::WARNING],
        ];
    }

    private function get_code_model_versions(): array
    {
        return [2 => 'v2 (embedded Tx library, multi-bus)', 1 => 'v1 (Tx library)'];
    }
}
