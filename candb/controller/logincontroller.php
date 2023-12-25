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

final class User {
    public $username, $fullName, $email;
    public $roles;
}

final class LoginController extends BaseController
{
    const ROLE_USER = 'PROTODB_USER';
    const ROLE_ADMIN = 'PROTODB_ADMIN';

    public function __construct(DB $db)
    {
        parent::__construct(false);
    }

    /**
     * @param string $username
     * @param string $password
     * @return User
     */
    private function authorizeUser(string $username, string $password): User {
        return $this->authorizeUserWithMethod($username, $password, $this->get_auth_method());
    }

    /**
     * @param string $username
     * @param string $password
     * @param AuthMethod $method
     * @return User
     */
    private function authorizeUserWithMethod(string $username, string $password, AuthMethod $method): User {
        if ($method instanceof AuthMethodNoAuthorization) {
            $user = new User();
            $user->username = $username;
            $user->roles = [self::ROLE_USER, self::ROLE_ADMIN];
            return $user;
        }
        else {
            throw new \InvalidArgumentException("Invalid authorization method for this operation");
        }
    }

    public function handle_login()
    {
        // This page is relevant only when username/password authentication is being used -- and this is currently not possible.
        // Otherwise send the user away, they should be redirected appropriately.
        header("Location: " . $GLOBALS['base_path']);
        exit();

        /*
        if (isset($_POST['username']) && isset($_POST['password'])) {
            $username = filter_input(INPUT_POST, "username", FILTER_SANITIZE_STRING);
            $password = filter_input(INPUT_POST, "password", FILTER_SANITIZE_STRING);

            try {
                $user = $this->authorizeUser($username, $password);

                $_SESSION['user'] = $user;
                $_SESSION['username'] = $user->username;
                $_SESSION['is_admin'] = in_array(self::ROLE_ADMIN, $user->roles, true);

                // Will be either empty (to go to main dashboard) or will contain a relative URL to go to.
                // Note that this is not validated in any way. XSS/CSRF risk?
                if ($_POST['goto'])
                    header("Location: " . $_POST['goto']);
                else
                    header("Location: " . $GLOBALS['base_path']);

                exit();
            }
            catch (AuthException $ex) {
                $this->add_message(htmlentities($ex->getMessage(), ENT_QUOTES) . '<br><br>Please try again.', 'danger', false);
            }
        }

        return 'views/login';
        */
    }

    public function handle_logout()
    {
        unset($_SESSION['user']);
        unset($_SESSION['username']);
        unset($_SESSION['is_admin']);

        // A post-logout redirect URL can be configured to facilitate single sign-out etc.
        // When using an OIDC reverse proxy, the usual flow would be /candb/logout -> /candb/oauth/logout -> https://oidc-provider/logout -> /candb/
        $logout_url = getenv("PROTODB_LOGOUT_REDIRECT_URL");
        if ($logout_url === false) {
            header('Location: ' . $GLOBALS['base_path']);
        }
        else {
            header('Location: ' . $logout_url);
        }
    }
}
