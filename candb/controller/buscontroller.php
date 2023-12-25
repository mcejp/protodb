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

use candb\model\Bus;
use candb\PluginError;
use candb\service\BusService;
use candb\service\exception\EntityNotFoundException;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\PythonInvoker;
use candb\service\UnitsService;

final class BusController extends BaseController
{
    private $bus_service, $messages, $packages, $units;

    public static function url(Bus $bus) { return $GLOBALS['base_path'] . "buses/{$bus->id}"; }

    public function __construct(BusService $bus_service,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units)
    {
        parent::__construct();
        $this->bus_service = $bus_service;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    /**
     * @throws PluginError
     * @throws \ReflectionException
     */
    public function handle_index(int $bus_id): HttpResult
    {
        try {
            $bus = $this->bus_service->by_id($bus_id);
        }
        catch (EntityNotFoundException $exception) {
            return HttpResult::with_response_code(HttpResult::NOT_FOUND_CODE);
        }

        $package = $this->packages->byId($bus->package_id);

        $message_ids = $this->messages->get_ids_associated_to_bus($bus_id);
        $messages = $this->messages->get_by_ids($message_ids, with_buses: false, with_fields: true);

        $num_fields_total = 0;
        $useful_bits_total = 0;
        $useful_bandwidth_total = 0;
        $total_bandwidth = 0;

        foreach ($messages as $message) {
            $stats = $message->get_bandwidth_statistics();
            $num_fields_total += $stats->num_fields;
            $useful_bits_total += $stats->useful_bits;
            $useful_bandwidth_total += $stats->useful_bandwidth;
            $total_bandwidth += $stats->total_bandwidth;
        }

        foreach ($messages as &$message) {
            $stats = $message->get_bandwidth_statistics();
            $id_hex = $message->id_to_hex_string();

            $message = (array)$message;
            $message['id_hex'] = $id_hex;
            $message['stats'] = $stats;
        }

        $variables = [
            'package' => $package,
            'bus' => $bus,
            'by_unit' => [['unit_name' => 'Overall', 'messages' => array_values($messages)]],

            'num_fields_total' => $num_fields_total,
            'useful_bits_total' => $useful_bits_total,
            'useful_bandwidth_total' => $useful_bandwidth_total,
            'total_bandwidth' => $total_bandwidth,

            'BREADCRUMB_PATH' => [
                ['title' => $package->title(), 'url' => $package->url()],
                ['title' => $bus->title(), 'url' => $bus->url()],
            ],

            "_globals" => $this->get_globals_for_template_renderer(),
        ];

        $invoker = new PythonInvoker();
        $result = $invoker->call(["-m", "protodb.webui.render_template", "bus"], null, null,
            json_encode($variables));

        if ($result->status === 0) {
            return new HttpResult([], $result->stdout);
        }
        else {
            throw new PluginError('View rendering failed', $result->stderr);
        }
    }
}
