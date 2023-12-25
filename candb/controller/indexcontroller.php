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

require_once 'vendor/Parsedown.php';

use candb\PluginError;
use candb\service\exception\EntityNotFoundException;
use candb\service\MessagesService;
use candb\service\PackagesService;
use candb\service\PythonInvoker;
use candb\service\UnitsService;

final class IndexController extends BaseController
{
    private $packages;
    private $units;
    private $messages;

    public function __construct(MessagesService $messages,
                                PackagesService $packages,
                                UnitsService $units) {
        parent::__construct();
        $this->messages = $messages;
        $this->packages = $packages;
        $this->units = $units;
    }

    public function handle_changelog()
    {
        $changelog_markdown = file_get_contents(__DIR__ . '/../../CHANGELOG.md');

        $parsedown = new \Parsedown();
        $changelog_html = $parsedown->text($changelog_markdown);

        return [
            'path' => 'views/app_changelog',
            'modelpath' => [],
            'changelog_html' => $changelog_html,
        ];
    }

    public function handle_index(?int $new_dashboard)
    {
        if ($new_dashboard === 1) {
            $_SESSION["new_dashboard"] = 1;
        }
        else if ($new_dashboard === 0) {
            $_SESSION["new_dashboard"] = 0;
        }

        if (!isset($_SESSION["new_dashboard"]) || $_SESSION["new_dashboard"] === 1) {
            return $this->handle_new_dashboard();
        }
        else {
            $all_packages = $this->packages->get_all(with_nodes: true);
            $all_units = $this->units->allUnitNames();
            $all_messages = $this->messages->allMessageNames();

            return [
                'path' => 'views/overview',
                'modelpath' => [],
                'all_packages' => $all_packages,
                'all_units' => $all_units,
                'all_messages' => $all_messages,
            ];
        }
    }

    public function handle_new_dashboard(): HttpResult
    {
        // FIXME: order undefined!
        $all_package_names = $this->packages->allPackageNames();

        $yaml_config = $this->get_yaml_config();

        $packages_pinned = [];
        $packages_other = [];
        $skip = [];

        // This will *not* raise any error if either configuration key is undefined
        $pinned_package_defs = $yaml_config['dashboard']['pinned_packages'] ?? [];

        foreach ($pinned_package_defs as $pin) {
            try {
                $p = $this->packages->by_name($pin['name'], true, false, true);
            }
            catch (EntityNotFoundException $ex) {       // TODO[PHP8]: can now catch without variable
                // Just ignore not-found packages
                continue;
            }

            $packages_pinned[] = ['package' => $p, 'color' => $pin['color'] ?? 'transparent'];
            $skip[] = $pin['name'];
        }

        foreach ($all_package_names as $package_name) {
            if (!in_array($package_name, $skip)) {
                $p = $this->packages->by_name($package_name, true, false, true);
                $packages_other[] = $p;
            }
        }

        $variables = [
            'links' => $yaml_config['dashboard_links'],
            "packages_pinned" => $packages_pinned,
            "packages_other" => $packages_other,

            "_globals" => $this->get_globals_for_template_renderer(),
        ];

        $invoker = new PythonInvoker();
        $result = $invoker->call(["-m", "protodb.webui.dashboard"], null, null,
            json_encode($variables));

        if ($result->status === 0) {
            return new HttpResult([], $result->stdout);
        }
        else {
            throw new PluginError('View rendering failed', $result->stderr);
        }
    }

    private function get_yaml_config()
    {
        $input = @fopen('config/config.yml', 'r');

        if (!is_resource($input)) {
            $input = @fopen('config/config.default.yml', 'r');
        }

        $parsed = yaml_parse(stream_get_contents($input));
        fclose($input);
        return $parsed;
    }
}
