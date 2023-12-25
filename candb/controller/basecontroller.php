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

use candb\ui\GUICommon;

interface AuthMethod {
};

class AuthMethodHeader implements AuthMethod {
};

class AuthMethodNoAuthorization implements AuthMethod {
};

class BaseController
{
    public const ADMIN_ROLE = "candb.admin";
    public const USER_ROLE = "candb.user";

    private const DEFAULT_USERNAME = "candb";   // used only for AuthMethodNoAuthorization

    private $require_login;
    private $auth_method;

    private $currentUsername;

    private $messages = [];

    /**
     * @param bool $require_login
     * @throws \Exception
     */
    public function __construct(bool $require_login = true)
    {
        $this->require_login = $require_login;

        // TODO: all of this logic should be encapsulated into some SessionManager class
        $this->auth_method = self::parse_auth_method(getenv("PROTODB_LOGIN_METHOD"));

        if (isset($_SESSION['username']))
            $this->setCurrentUsername($_SESSION['username']);
    }

    public function add_message(string $text, string $class_ = 'default', bool $escape = true): void {
        if ($escape)
            $text = htmlentities($text);

        $this->messages[] = ['text' => $text, 'class' => $class_];
    }

    /**
     * @param string $target
     * @param string|null $authorization
     * @return HttpResult|null null to proceed, or a HttpResult to stop processing early
     * @throws \Exception
     */
    public function before_handle(string $target, ?string $authorization): ?HttpResult
    {
        $auth_method = $this->get_auth_method();

        if ($auth_method instanceof AuthMethodNoAuthorization) {
            $this->setCurrentUsername(self::DEFAULT_USERNAME);

            // TODO: we only set this here because other code reads it directly
            $_SESSION['username'] = $this->getCurrentUsername();
            $_SESSION['is_admin'] = true;
            return null;
        }

        if ($this->require_login) {
            if (!isset($_SESSION['user'])) {
                if ($auth_method instanceof AuthMethodHeader) {
                    // 3rd-party authorization provider; trust what we receive and copy it into the session
                    $user_roles = explode(",", $_SERVER["HTTP_X_AUTH_ROLES"]);

                    if (!in_array(self::USER_ROLE, $user_roles)) {
                        return HttpResult::with_response_code(HttpResult::UNAUTHORIZED_CODE);
                    }

                    // TODO: isn't all of this $_SESSION stuff obsolete when using proxy-based authentication?
                    $_SESSION['user'] = true;
                    $_SESSION['username'] = $_SERVER['HTTP_X_AUTH_USERNAME'];
                    $_SESSION['is_admin'] = in_array(self::ADMIN_ROLE, $user_roles);

                    $this->setCurrentUsername($_SERVER['HTTP_X_AUTH_USERNAME']);
                }
                else {
                    // Otherwise, redirect to login page

                    // This might not be the best idea after all.
                    // Maybe we should just redirect to $base_path . $request_uri
                    $forwarded_uri = $this->get_client_requested_uri();

                    if ($forwarded_uri == $GLOBALS['base_path'])
                        header("location: " . $GLOBALS['base_path'] . "login");
                    else
                        header("location: " . $GLOBALS['base_path'] . "login?goto=" . urlencode($forwarded_uri));

                    exit();
                }
            }
        }

        return null;
    }

    public function build_page_title($view): string {
        global $is_devel;

        $title = '';

        if (array_key_exists('modelpath', $view)) {
            foreach ($view['modelpath'] as $entity) {
                $title = $entity->title() . ' | ' . $title;
            }
        }

        if ($is_devel) {
            $title .= 'DEV ';
        }

        $title .= 'ProtoDB';
        return $title;
    }

    public function get_client_requested_uri(): string {
        if (isset($_SERVER['HTTP_X_FORWARDED_URI']))
            return $_SERVER['HTTP_X_FORWARDED_URI'];
        else
            return $_SERVER['REQUEST_URI'];
    }

    public function getCurrentUsername(): string {
        // throws if null!
        return $this->currentUsername;
    }

    protected function get_globals_for_template_renderer(): array
    {
        global $app_version;
        global $is_devel;

        return [
            'APP_VERSION' => $app_version,
            'BASE_PATH' => $GLOBALS['base_path'],
            'BRANDNAME' => 'ProtoDB',
            'DISPLAY_INSTANCE_NAME' => $this->should_show_instance_name(),
            'INSTANCE_NAME' => $this->getInstanceName(),
            'IS_DEVEL' => $is_devel,
        ];
    }

    public function getInstanceName(): ?string {
        $instance_name = getenv("PROTODB_INSTANCE_NAME");

        if ($instance_name === false)
            return null;
        else
            return $instance_name;
    }

    public function get_auth_method(): AuthMethod {
        return $this->auth_method;
    }

    private static function parse_auth_method(string $login_method_str): AuthMethod {
        $tokens = explode(';', $login_method_str);
        $method = $tokens[0];

        switch ($method) {
            case "arbitrary":
                return new AuthMethodNoAuthorization();

            case "header":
                return new AuthMethodHeader();

            default:
                throw new \Exception("PROTODB_LOGIN_METHOD missing or invalid");
        }
    }

    public function popover(string $id): void {
        GUICommon::popover($id);
    }

    public function render_breadcrumb(array $stack): void {
        echo '</div>';
        echo '<div class="fixed-header">';
        echo '<div class="breadcrumb-container">';
        echo '<ol class="container breadcrumb">';
        echo '<li><strong><a href="'.$GLOBALS['base_path'].'">ProtoDB';

        if ($this->should_show_instance_name()) {
            echo '&ensp;'. $this->getInstanceName();
        }

        echo '</a></strong>';

        echo '</li>';

        for ($i = 0; $i < count($stack); $i++) {
            if (!$stack[$i])
                continue;

            $url = $stack[$i]->url();

            if ($url)
                echo "<li><a href='".htmlentities($url, ENT_QUOTES)."'>".htmlentities($stack[$i]->title())."</a></li>";
            else
                echo "<li>".htmlentities($stack[$i]->title())."</li>";
        }

        echo '<a href="'.$GLOBALS['base_path'].'logout" class="pull-right" title="Log out"><span class="glyphicon glyphicon-off"></span></a>';
        echo '</ol>';
        echo '</div>';
    }

    public function render_list(array $list, callable $function): void {
        foreach ($list as $item)
            $function($item);

        if (!$list)
            echo '<p class="text-center text-muted">No data.</p><br>';
    }

    public function render_messages(): void {
        echo '</div>';
        echo '<div class="container">';

        foreach ($this->messages as $message) {
            echo "<div class='alert alert-{$message['class']}'>" . $message['text'] . "</div>";
        }
    }

    public function setCurrentUsername(string $id): void {
        $this->currentUsername = $id;
    }

    public function should_show_instance_name(): bool {
        global $is_devel;

        return $is_devel || boolval(getenv("PROTODB_SHOW_INSTANCE_NAME"));
    }

    public function user_is_admin(): bool {
        return $_SESSION['is_admin'];
    }
}
