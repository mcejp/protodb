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
use candb\model\Package;
use candb\model\Unit;
use candb\service\exception\EntityNotFoundException;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\UnitsService;
use candb\service\drc\DrcService;

final class DrcController extends BaseController
{
    private $drc_service;
    private $messages_service;
    private $packages_service;
    private $units_service;

    public function __construct(DrcService $drc_service,
                                MessagesService $messages_service,
                                PackagesService $packages_service,
                                UnitsService $units_service)
    {
        parent::__construct();
        $this->drc_service = $drc_service;
        $this->messages_service = $messages_service;
        $this->packages_service = $packages_service;
        $this->units_service = $units_service;
    }

    public function handle_all(): array
    {
        $incidents = $this->drc_service->all_active_incidents();

        return [
            'path' => 'views/drc',
            'modelpath' => [],
            'incidents' => $incidents,
            'run_url' => '',
        ];
    }

    /**
     * @param int $incident_id
     * @return array|HttpResult
     */
    public function handle_incident(int $incident_id)
    {
        $incident = $this->drc_service->incident_by_id($incident_id);

        if ($incident === null) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        return [
            'path' => 'views/drc-incident-details',
            'modelpath' => [/*$pkg, $unit*/],
            'incidents' => [$incident],
            'run_url' => '',
        ];
    }

    /**
     * @param int $package_id
     * @param int $run
     * @return array|HttpResult
     * @throws EntityNotFoundException
     * @throws \ReflectionException
     * @throws \candb\SubprocessException
     */
    public function handle_package(int $package_id, int $run = 0)
    {
        try {
            $pkg = $this->packages_service->byId($package_id);
        }
        catch (EntityNotFoundException $exception) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        if ($run) {
            $num_incidents = $this->drc_service->run_for_package($package_id);

            $this->add_message("DRC finished with {$num_incidents[DrcIncident::ERROR]} errors and {$num_incidents[DrcIncident::WARNING]} warnings.", 'info');
        }

        $incidents = $this->drc_service->incidents_by_package($package_id);

        return [
            'path' => 'views/drc',
            'modelpath' => [$pkg],
            'incidents' => $incidents,
            'run_url' => '?run=1',
        ];
    }

    /**
     * @return array|HttpResult
     * @throws \ReflectionException
     * @throws EntityNotFoundException
     */
    public function handle_unit(int $unit_id)
    {
        try {
            $unit = $this->units_service->byId($unit_id);
        }
        catch (EntityNotFoundException $exception) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        $pkg = $this->packages_service->byId($unit->package_id);

        $incidents = $this->drc_service->incidents_by_unit($unit_id);

        return [
            'path' => 'views/drc',
            'modelpath' => [$pkg, $unit],
            'incidents' => $incidents,
            'run_url' => '',
        ];
    }

    /**
     * @return array|HttpResult
     * @throws EntityNotFoundException
     * @throws \ReflectionException
     */
    public function handle_message(int $message_id)
    {
        try {
            $message = $this->messages_service->byId($message_id);
        }
        catch (EntityNotFoundException $exception) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        $unit = $this->units_service->byId($message->node_id);
        $pkg = $this->packages_service->byId($unit->package_id);

        $incidents = $this->drc_service->incidents_by_message($message_id);

        return [
            'path' => 'views/drc',
            'modelpath' => [$pkg, $unit, $message],
            'incidents' => $incidents,
            'run_url' => '',
        ];
    }

    public function build_message_link(DrcIncident $incident): string
    {
        if ($incident->message_id)
            return '<a href="' . htmlentities(Message::s_url($incident->message_id), ENT_QUOTES) . '">' . htmlentities($incident->message_name, ENT_QUOTES) . '</a>';
        else
            return '';
    }

    public function build_package_link(DrcIncident $incident): string
    {
        if ($incident->package_id)
            return '<a href="' . htmlentities(Package::s_url($incident->package_id), ENT_QUOTES) . '">' . htmlentities($incident->package_name, ENT_QUOTES) . '</a>';
        else
            return '';
    }

    public function build_unit_link(DrcIncident $incident): string
    {
        if ($incident->node_id)
            return '<a href="' . htmlentities(Unit::s_url($incident->node_id), ENT_QUOTES) . '">' . htmlentities($incident->unit_name, ENT_QUOTES) . '</a>';
        else
            return '';
    }
}
