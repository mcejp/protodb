<?php
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
use candb\model\Unit;
use candb\service\drc\DrcService;
use candb\service\exception\EntityNotFoundException;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;

final class PackageController extends BaseController
{
    private $drc_service, $messages, $packages, $units;

    public function __construct(DrcService $drc_service,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units)
    {
        parent::__construct();
        $this->drc_service = $drc_service;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    /**
     * @return array|HttpResult
     * @throws \ReflectionException
     */
    public function handle_view(int $package_id)
    {
        try {
            $pkg = $this->packages->byId($package_id, true, true, true);
        }
        catch (EntityNotFoundException $exception) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        // Get number of DRC incidents by type
        $incident_stats = $this->drc_service->incident_counts_by_package($package_id);

        return [
            'path' => 'views/package',
            'modelpath' => [$pkg],
            'package_id' => $package_id,
            'pkg' => $pkg,

            'drc_num_errors' => $incident_stats[DrcIncident::ERROR],
            'drc_num_warnings' => $incident_stats[DrcIncident::WARNING],
        ];
    }

    /**
     * @param int $package_id
     * @return array|HttpResult
     * @throws EntityNotFoundException
     * @throws \ReflectionException
     */
    public function handle_view_communication_matrix(int $package_id)
    {
        try {
            $pkg = $this->packages->byId($package_id, true, false, false);
        }
        catch (EntityNotFoundException $exception) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        $units = [];

        foreach ($pkg->units as $unit)
            $units[] = $this->units->byId($unit->id, false, true);

        // rows = senders
        // columns = receivers

        $rows = [];

        foreach ($units as $sender) {
            $row = [];

            foreach ($units as $receiver) {
                // find all messages sent by sender and received by receiver

                $matches = [];

                foreach ($sender->sent_messages as $message) {
                    if ($this->is_received_by($message['message'], $receiver))
                        $matches[] = $message['message'];
                }

                $row[] = $matches;
            }

            $rows[] = $row;
        }

        return [
            'path' => 'views/communication_matrix',
            'modelpath' => [$pkg],
            'pkg' => $pkg,
            'units' => $units,
            'rows' => $rows,
        ];
    }

    private function is_received_by(Message $message, Unit $receiver): bool
    {
        foreach ($receiver->received_messages as $received_message)
            if ($message->id == $received_message['message_id'])
                return true;

        return false;
    }
}
