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

use candb\JsonExporter2;
use candb\model\Bus;
use candb\model\EnumType;
use candb\model\Message;
use candb\model\Package;
use candb\model\Unit;
use candb\PluginError;
use candb\service\BusService;
use candb\service\EntityCache;
use candb\service\EnumTypesService;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\PythonInvoker;
use candb\service\UnitsService;
use candb\SubprocessException;
use ZipArchive;

// https://stackoverflow.com/a/2050909
function recurse_copy($src,$dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recurse_copy($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// https://stackoverflow.com/a/3338133
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir."/".$object))
                    rrmdir($dir."/".$object);
                else
                    unlink($dir."/".$object);
            }
        }
        rmdir($dir);
    }
}

function tempdir($dir=false,$prefix='php') {
    $tempfile=tempnam(sys_get_temp_dir(),'');
    if (file_exists($tempfile)) { unlink($tempfile); }
    mkdir($tempfile);
    if (is_dir($tempfile)) { return $tempfile; }
}

// https://stackoverflow.com/a/1334949
function zip($destination, $source)
{
    $zip = new ZipArchive();
    if ($zip->open($destination, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) !== TRUE) {
        throw new \Exception("Failed to create archive");
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source) === true) {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source),
            \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file => $file_info) {
            /** @var string $file */
            $file = str_replace('\\', '/', $file);

            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) ) {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file) === true) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            else if (is_file($file) === true) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    }
    else if (is_file($source) === true) {
        $zip->addFromString(basename($source), file_get_contents($source));
    }

    if ($zip->status != ZIPARCHIVE::ER_OK)
        throw new \Exception("Failed to write files to zip");

    return $zip->close();
}

final class Group
{
    public $name;
    public $unit;
    public $messages = [];

    public function __construct($name, ?Unit $unit)
    {
        $this->name = $name;
        $this->unit = $unit;
    }
}

final class ExportController extends BaseController
{
    private $bus_service;
    private $enum_types;
    private $packages;
    private $units;
    private $messages;

    private $unit_name_with_package_name_cache = [];

    private const undefined_bus_name = 'UNDEFINED';

    public static function url_export_bus(int $bus_id, string $format) {
        return $GLOBALS['base_path'] . "buses/$bus_id/export-" . $format;
    }

    public function __construct(BusService $bus_service,
                                EnumTypesService $enum_types,
                                MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units) {
        parent::__construct();
        $this->bus_service = $bus_service;
        $this->enum_types = $enum_types;
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    private function get_array_for_enum_type(EnumType $enum_type): array
    {
        // TODO: cache this!
        $unit = $this->units->byId((int)$enum_type->node_id);

        $enum_type_json = ['name' => $enum_type->name, 'description' => $enum_type->description, 'items' => []];

        foreach ($enum_type->items as $item)
            $enum_type_json['items'][] = ['name' => $item->name, 'value' => $item->value, 'description' => $item->description];

        return $enum_type_json;
    }

    private function get_array_for_message(Message $message, bool $use_absolute_names): array {
        $message->layout();

        if ($message->bus_id !== null) {
            // TODO: cache this!
            $bus_name = $this->bus_service->by_id($message->bus_id)->name;
        }
        else {
            $bus_name = self::undefined_bus_name;
        }

        $message_json_ = [
            'bus' => $bus_name,
            'id' => $message->get_can_id() !== null ? (int)$message->get_can_id() : null,
            'frame_type' => $message->get_frame_type(),
            'name' => $message->name,
            'comments' => explode("\n", $message->description ?: ""),
            'sentBy' => [],
            'receivedBy' => [],
            'timeout' => $message->timeout !== NULL ? (int) $message->timeout : NULL,
            'tx_period' => $message->tx_period !== NULL ? (int) $message->tx_period : NULL,
            'fields' => [],
            'length' => $message->num_bytes
        ];

        foreach ($message->fields as $field) {
            if (is_numeric($field->type)) {
                // TODO: cache this!
                $enum_type = $this->enum_types->byId($field->type);
                $unit = $this->units->byId((int)$enum_type->node_id);

                if ($use_absolute_names) {
                    $unit_name = $this->get_unit_name_with_package_name($unit);
                }
                else {
                    $unit_name = $unit->name;
                }

                $type = 'enum ' . $unit_name . '_' . $enum_type->name;
            }
            else
                $type = $field->type;

            $regex = '/([0-9.]+)\/([0-9.]+)/';
            if (is_numeric($field->factor))
                $factor_num = (float) $field->factor;
            else if (preg_match($regex, $field->factor ?: '', $matches))
                $factor_num = (float) $matches[1] / (float) $matches[2];
            else
                $factor_num = null;

            $message_json_['fields'][] = [
                'name' => $field->name,
                'comments' => [$field->description],
                'type' => $type,
                'bits' => (int)$field->bit_size,
                'count' => (int)$field->array_length,
                'start_bit' => (int)$field->start_bit,
                'unit' => $field->unit,             // TODO: check -- should be correctly NULL if unset
                'factor' => $field->factor,         // TODO: check -- should be correctly NULL if unset
                'factor_num' => $factor_num,
                'offset' => $field->offset,         // TODO: check -- should be correctly NULL if unset
                'min' => $field->min,               // TODO: check -- should be correctly NULL if unset
                'max' => $field->max,               // TODO: check -- should be correctly NULL if unset
            ];
        }

        return $message_json_;
    }

    // Currently used for:
    //  - package export (JSON 1.0)
    //  - candb-dbc-export
    private function get_json_for_package(Package $package, bool $pretty, ?int $bus_id): string {
        // FIXME: everything!!
        $groups = [];

        $all_messages = [];
        foreach ($package->units as $unit) {
            $unit = $this->units->byId($unit->id, false, true);
            $all_messages = array_merge($all_messages, $unit->sent_messages);
            $all_messages = array_merge($all_messages, $unit->received_messages);
        }

        // Loop through all messages relevant to this unit
        foreach ($all_messages as $row) {
            $message = $this->messages->byId($row['message_id'], true);

            if ($bus_id !== null && $message->bus_id !== $bus_id)
                continue;

            if (!isset($groups[$message->node_id])) {
                $owner = $this->units->byId($message->node_id, false, false, true);

                // Build unit name for the message owner
                $owner_name = $message->owner_name;

                $groups[$message->node_id] = new Group($owner_name, $owner);
            }

            $group = $groups[$message->node_id];

            // Create a record for this message if it doesn't exist yet
            if (!isset($group->messages[$message->id])) {
                $group->messages[$message->id] = $this->get_array_for_message($message, false);
            }

            $message_json = &$group->messages[$message->id];

            if ($row['operation'] == 'SENDER')     // ugh!
                $message_json['sentBy'][] = $this->units->byId($row['node_id'])->name;
            else
                $message_json['receivedBy'][] = $this->units->byId($row['node_id'])->name;
        }

        // Build the final groups JSON
        $groups_json = [];

        foreach ($groups as $node_id => $group) {
            $group_json = ['name' => $group->name, 'comments' => $group->unit ? explode("\n", $group->unit->description) : NULL, 'enum_types' => [], 'messages' => array_values($group->messages)];

            // Enum types
            if ($group->unit) {
                foreach ($group->unit->enum_types as $enum_type) {
                    $enum_type = $this->enum_types->byId($enum_type->id);
                    $group_json['enum_types'][] = $this->get_array_for_enum_type($enum_type);
                }
            }

            $groups_json[] = $group_json;
        }

        return json_encode($groups_json, $pretty ? JSON_PRETTY_PRINT : 0);
    }

    // Currently used for:
    //  - unit export (JSON 1.0)
    //  - candb-codegen
    // Note: Unit must have been loaded with enums!
    private function get_json_for_unit(Unit $unit, bool $pretty, bool $use_absolute_names): string {
        assert($unit->enum_types !== null);
        assert($unit->sent_messages !== null);
        assert($unit->received_messages !== null);

        // Groups are namespaces for CAN messages -- each group is some unit
        $groups = [];

        if ($use_absolute_names) {
            $unit_name = $this->get_unit_name_with_package_name($unit);
        }
        else {
            $unit_name = $unit->name;
        }

        $groups[$unit->id] = new Group($unit_name, $unit);

        // Loop through all messages relevant to this unit
        foreach (array_merge($unit->sent_messages, $unit->received_messages) as $row) {
            $message = $this->messages->byId($row['message_id'], true);

            if (!isset($groups[$message->node_id])) {
                $owner = $this->units->byId($message->node_id, false, false, true);

                // Build unit name for the message owner
                if (!$use_absolute_names || !$owner)
                    $owner_name = $message->owner_name;
                else
                    $owner_name = $this->get_unit_name_with_package_name($owner);

                $groups[$message->node_id] = new Group($owner_name, $owner);
            }

            $group = $groups[$message->node_id];

            // Create a record for this message if it doesn't exist yet
            if (!isset($group->messages[$message->id])) {
                $group->messages[$message->id] = $this->get_array_for_message($message, $use_absolute_names);
            }

            $message_json = &$group->messages[$message->id];

            if ($row['operation'] == 'SENDER')
                $message_json['sentBy'][] = $unit_name;
            else
                $message_json['receivedBy'][] = $unit_name;
        }

        // Build the final groups JSON
        $groups_json = [];

        foreach ($groups as $node_id => $group) {
            // TODO: This should be like an intersection of the generating unit's buses and this unit's buses, right?
            $buses = array_map(fn($bus) => $bus->name, $this->bus_service->by_unit_id($group->unit->id));
            $buses[] = self::undefined_bus_name;

            $group_json = [
                'name' => $group->name,
                'buses' => $buses,
                'enum_types' => [],
                'messages' => array_values($group->messages)
            ];
            usort($group_json['messages'], function ($a, $b) { return ($a['id'] ? $a['id'] : 0) - ($b['id'] ? $b['id'] : 0); });

            // Enum types
            if ($group->unit) {
                foreach ($group->unit->enum_types as $enum_type) {
                    $enum_type = $this->enum_types->byId($enum_type->id);
                    $group_json['enum_types'][] = $this->get_array_for_enum_type($enum_type);
                }
            }

            $groups_json[] = $group_json;
        }

        usort($groups_json, function ($a, $b) { return strcmp($a['name'], $b['name']); });

        return json_encode($groups_json, $pretty ? JSON_PRETTY_PRINT : 0);
    }

    private function get_unit_name_with_package_name(Unit $unit) {
        if (!array_key_exists($unit->id, $this->unit_name_with_package_name_cache)) {
            $this->unit_name_with_package_name_cache[$unit->id] = $this->packages->byId($unit->package_id)->name . '_' . $unit->name;
        }

        return $this->unit_name_with_package_name_cache[$unit->id];
    }

    public function handle_bus_dbc(int $bus_id) {
        $package_id = $this->bus_service->by_id($bus_id)->package_id;

        return $this->handle_dbc($package_id, $bus_id);
    }

    public function handle_dbc(int $package_id, ?int $bus_id = null) {
        try {
            return $this->handle_dbc_or_throw_exception($package_id, $bus_id);
        }
        catch (PluginError $exception) {
            return [
                'path' => 'views/plugin-error',
                'message' => $exception->getShortMessage(),
                'stderr' => $exception->getStderr()
            ];
        }
    }

    public function handle_dbc_or_throw_exception(int $package_id, ?int $bus_id) {
        $package = $this->packages->byId($package_id, true);
        if (!$package) throw new Exception("Invalid package_id");

        $json = $this->get_json_for_package($package, false, $bus_id);

        if ($bus_id === null) {
            $bus = null;
            $download_filename = $package->name . '.dbc';
        }
        else {
            $bus = $this->bus_service->by_id($bus_id);
            $download_filename = $package->name . '_' . $bus->name . '.dbc';
        }

        return $this->json_to_dbc($json, $download_filename, $bus);
    }

    public function handle_json(int $unit_id): HttpResult {
        $unit = $this->units->byId($unit_id, true, true, true) or die("Invalid unit_id");

        $regex = '/(^|\s)\-fcodegen\-unit\-versions($|\s)/';
        if ($unit->advanced_options && preg_match($regex, $unit->advanced_options))
            $use_absolute_names = true;
        else
            $use_absolute_names = false;

        $json = $this->get_json_for_unit($unit, true, $use_absolute_names);
        return new HttpResult(['Content-Type' => 'application/json',
                               'Content-Disposition' => 'attachment; filename=' . $this->get_unit_name_with_package_name($unit) . '.json'],
                              $json);
    }

    public function handle_json2_buses_of_package(int $package_id): HttpResult {
        $cache = new EntityCache();
        $package = $cache->get_package_by_id($package_id, $this->packages);

        $exporter = new JsonExporter2($this->bus_service, $this->enum_types, $this->messages, $this->packages, $this->units);
        $model = $exporter->export_buses_of_package($package_id, $cache);

        return new HttpResult(['Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename=' . $package->name . '.json'],
            json_encode($model, JSON_PRETTY_PRINT));
    }

    public function handle_json2_buses_of_package_protodb(int $package_id): HttpResult {
        $package = $this->packages->byId($package_id);

        $invoker = new PythonInvoker();
        $result = $invoker->call(["-m", "protodb.export.export_json2", $package->name]);

        if ($result->status !== 0) {
            // TODO: Refactor as PythonInvoker::check_call
            throw new SubprocessException($result->status, $result->stdout, $result->stderr);
        }

        $model = json_decode($result->stdout);

        return new HttpResult(['Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename=' . $package->name . '.json'],
            json_encode($model, JSON_PRETTY_PRINT));
    }

    public function handle_unit_dbc(int $unit_id) {
        try {
            $unit = $this->units->byId($unit_id, false, true, true);
            if (!$unit) throw new \Exception("Invalid unit_id");

            $package = $this->packages->byId($unit->package_id);

            $json = $this->get_json_for_unit($unit, false, true);
            $download_filename = $package->name . '_' . $unit->name . '.dbc';

            return $this->json_to_dbc($json, $download_filename, null);
        }
        catch (PluginError $exception) {
            return [
                'path' => 'views/plugin-error',
                'message' => $exception->getShortMessage(),
                'stderr' => $exception->getStderr()
            ];
        }
    }

    public function handle_package_json(int $package_id, ?int $bus_id = null): HttpResult {
        $package = $this->packages->byId($package_id, true);
        if (!$package) throw new Exception("Invalid package_id");

        $json = $this->get_json_for_package($package, true, $bus_id);
        return new HttpResult(['Content-Type' => 'application/json',
                               'Content-Disposition' => 'attachment; filename=' . $package->name . '.json'],
                              $json);
    }

    public function handle_tx(int $unit_id) {
        $unit = $this->units->byId($unit_id, true, true, true) or die("Invalid unit_id");

        if ($unit->code_model_version !== 1 && $unit->code_model_version !== 2) {
            return [
                'path' => 'views/plugin-error',
                'message' => 'Code generation failed',
                'stderr' => 'Unsupported code model version',
            ];
        }

        $artifact_name = 'ProtoDB_' . $unit->name;

        $regex = '/(^|\s)\-fcodegen\-unit\-versions($|\s)/';
        if ($unit->advanced_options && preg_match($regex, $unit->advanced_options))
            $use_absolute_names = true;
        else
            $use_absolute_names = false;

        $json = $this->get_json_for_unit($unit, false, $use_absolute_names);

        $tmpfname = tempnam(sys_get_temp_dir(), 'candb');
        file_put_contents($tmpfname, $json);

        $tmpdirname = tempdir();

        if ($use_absolute_names) {
            $unit_name = $this->get_unit_name_with_package_name($unit);
        }
        else {
            $unit_name = $unit->name;
        }

        $invoker = new PythonInvoker();
        $result = $invoker->call(["candb-codegen/candb-generate-c.py", $tmpfname,
                                  "-u", $unit_name,
                                  "-O", $tmpdirname,
                                  "-x", $unit->code_model_version
                                 ], dirname(__FILE__) . '/../..');

        unlink($tmpfname);

        if ($unit->code_model_version === 2) {
            // Code Model v2: Add Tx library

            recurse_copy('candb-codegen/tx', $tmpdirname);
        }

        if ($result->status === 0) {
            //echo $stdout;

            $zipFile = tempnam(sys_get_temp_dir(), 'candb');
            zip($zipFile, $tmpdirname);
            rrmdir($tmpdirname);

            $headers = [
                'Pragma' => 'public',
                'Expires' => '0',
                'Cache-Control' => ['must-revalidate, post-check=0, pre-check=0',
                                    'private'],
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename=' . $artifact_name . '.zip',     // FIXME: must be escaped
                'Content-Transfer-Encoding' => 'binary',
                'Content-Length' => filesize($zipFile)
            ];

            return new HttpFileDownloadResult($headers, $zipFile, true);
        }
        else {
            rrmdir($tmpdirname);

            return [
                'path' => 'views/plugin-error',
                'message' => 'Code generation failed',
                'stderr' => $result->stderr,
            ];
        }
    }

    private function json_to_dbc($json, string $download_filename, ?Bus $bus) {
        $tmpfname1 = tempnam(sys_get_temp_dir(), 'candb1');
        $tmpfname2 = tempnam(sys_get_temp_dir(), 'candb2');
        file_put_contents($tmpfname1, $json);

        $cmdline = ["candb-dbc-export/convert-to-dbc.py", $tmpfname1, $tmpfname2];

        if ($bus !== null) {
            $cmdline[] = '--bus-name';
            $cmdline[] = $bus->name;
        }

        $invoker = new PythonInvoker();
        $result = $invoker->call($cmdline, dirname(__FILE__) . '/../..');

        unlink($tmpfname1);

        if ($result->status === 0) {
            $body = file_get_contents($tmpfname2);
            unlink($tmpfname2);

            return new HttpResult([
                'Content-Type' => 'application/dbc',
                'Content-Disposition' => 'attachment; filename=' . $download_filename
            ], $body);
        }
        else {
            throw new PluginError('DBC file generation failed', $result->stderr);
        }
    }
}
