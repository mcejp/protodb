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

$rendering_start = microtime(true);

$app_version = "HEAD";
$is_devel = boolval(getenv('PROTODB_DEVELOPMENT'));
$base_path = getenv("PROTODB_BASE_PATH");
$sendgrid_api_key = getenv("SENDGRID_API_KEY");
$errors_mailfrom = getenv("PROTODB_ERRORS_MAILFROM");
$errors_mailto = getenv("PROTODB_ERRORS_MAILTO");
$sentry_dsn = getenv("PROTODB_SENTRY_DSN");
if ($base_path === FALSE)
    $base_path = (!$is_devel) ? "/candb/" : "/candb-dev/";
$show_errors = $is_devel;

$GLOBALS['base_path'] = $base_path;

if ($show_errors) {
    ini_set('display_startup_errors', 1);
    ini_set('display_errors', 1);
    error_reporting(-1);
}

if (file_exists("COMMIT_SHA")) {
    $commit_sha = file_get_contents("COMMIT_SHA");
}
else {
    $commit_sha = null;
}

// Convert all notices/warnings/errors to exceptions
// Source: https://stackoverflow.com/a/32822054
function exception_error_handler($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}
set_error_handler("exception_error_handler");

// initialize autoloader & dependency injection
spl_autoload_extensions(".php");
spl_autoload_register();

require_once 'candb/service/tinysentryclient.php';
require_once 'vendor/Dice.php';
require_once 'vendor/getallheaders.php';

class Application {
    /** @var \Dice\Dice */ private $dice;

    public function __construct(bool $show_errors, ?string $errors_mailto)
    {
        $this->dice = new \Dice\Dice();
        $this->dice = $this->dice->addRule('\candb\DB', ['shared' => true]);

        //$dice->addRule('*', $general_rule);

        ini_set("session.cookie_lifetime", 5 * 3600);
        ini_set("session.gc_maxlifetime", 5 * 3600);

        session_set_cookie_params(5 * 3600);
        session_start();
        //setcookie(session_name(),session_id(),time()+$lifetime);
    }

    /**
     * @param string $controller
     * @param string $target
     * @param string $authorization_header
     * @throws ReflectionException
     * @throws Exception
     */
    public function handle_request(string $controller, string $target, ?string $authorization_header)
    {
        // retrieve controller
        /** @var \candb\controller\BaseController $controller */ $controller = $this->dice->create('candb\\controller\\' . $controller);

        $maybe_httpresult = $controller->before_handle($target, $authorization_header);

        // Early exit?
        if ($maybe_httpresult !== null) {
            $this->render_httpresult_and_exit($maybe_httpresult);
        }

        // handle the request
        $handler = 'handle_' . $target;

        $method = new ReflectionMethod($controller, $handler);
        $args = [];

        // set up handler arguments
        foreach ($method->getParameters() as $parameter) {
            // this stupid interface changes with every version of PHP... see https://github.com/php/php-src/pull/4839
            if ($parameter->getType()->isBuiltin() && $parameter->getType()->getName() === "int") {
                $arg = filter_input(INPUT_GET, $parameter->name, FILTER_VALIDATE_INT);

                if ($arg === FALSE) {
                    die('Invalid ' . $parameter->name);
                }
                else if ($arg === NULL) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $arg = $parameter->getDefaultValue();
                    }
                    else if ($parameter->getType()->allowsNull()) {
                        $arg = null;
                    }
                    else
                        die('Invalid ' . $parameter->name);
                }
                else {
                    $arg = (int)$arg;
                }

                $args[] = $arg;
            }
            else if ($parameter->getType()->getName() === 'candb\controller\HttpRequest') {
                $headers = getallheaders();
                $body = file_get_contents('php://input');

                $args[] = new \candb\controller\HttpRequest($headers, $body);
            }
            else {
                die('Unhandled argument ' . $parameter->name . ' of type ' . $parameter->getType()->getName());
            }
        }

        $result = $method->invokeArgs($controller, $args);
        $this->render_result($controller, $result);
    }

    private function render_httpresult_and_exit(\candb\controller\HttpResult $result): void {
        if ($result->response_code !== null) {
            http_response_code($result->response_code);
        }

        foreach ($result->headers as $key => $value) {
            // FIXME: Probably should escape value?
            header("$key: $value");
        }

        if ($result->body)
            echo $result->body;

        exit();
    }

    private function render_result($controller, $result): void
    {
        // render view
        if ($result === NULL) {
            exit();
        }
        else if ($result instanceof candb\controller\HttpResult) {
            $this->render_httpresult_and_exit($result);
        }
        else if ($result instanceof candb\controller\HttpFileDownloadResult) {
            foreach ($result->headers as $key => $value) {
                if (is_array($value)) {
                    header("$key: ${value[0]}");

                    for ($i = 1; $i < count($value); $i++) {
                        header("$key: ${value[$i]}", false);
                    }
                }
                else {
                    header("$key: $value");
                }
            }

            readfile($result->filename);

            if ($result->delete_after_download) {
                unlink($result->filename);
            }

            exit();
        }
        else if (is_array($result)) {
            $view = $result;

            /*foreach ($result as $key => $value) {
                ${$key} = $value;
            }*/
        }
        else if (is_string($result)) {
            $view = ['path' => $result];
        }
        else
            die();

        require_once(str_replace('\\', '/', $view['path']) . '.php');
    }
}

try {
    // get arguments
    $controller = filter_input(INPUT_GET, "controller") or die();
    $target = filter_input(INPUT_GET, "target") or die();

    $authorization_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;

    $app = new Application($show_errors, $errors_mailto);
    $app->handle_request($controller, $target, $authorization_header);
}
catch (\Throwable $exception) {
    http_response_code(500);
    //error_log($exception->getMessage());

    if ($sentry_dsn) {
        try {
            $client = new candb\service\TinySentryClient($sentry_dsn);

            $client->log_exception($exception,
                $_SERVER['HTTP_HOST'],
                $_SERVER['REQUEST_URI'],
                $commit_sha ?? ('candb ' . $app_version),
                getenv("PROTODB_INSTANCE_NAME")
            );
        }
        catch (\Throwable $new_exception) {
            error_log("ProtoDB: Failed to log error via Sentry: " . $new_exception->getMessage());
        }
    }

    if ($errors_mailto && $errors_mailfrom && $sendgrid_api_key) {
        $exception_message = $exception->getMessage();
        $exception_file = $exception->getFile();
        $exception_line = $exception->getLine();
        $exception_trace = $exception->getTraceAsString();

        $allheaders = getallheaders();
        $request_headers = implode("\n", array_map(
            fn($value, $key) => $key.': '.$value,
            array_values($allheaders),
            array_keys($allheaders))
        );

        $request_body = file_get_contents('php://input');

        if (strpos($request_body, 'password') !== false) {
            $request_body = substr($request_body, 0, strpos($request_body, 'password') + 9) . '[ POTENTIALLY SENSITIVE CONTENT TRIMMED ]';
        }

        $all_session_variables = print_r($_SESSION, true);

        $body = <<<END
# EXCEPTION
$exception_message
IN $exception_file:$exception_line

# TRACE
$exception_trace

# REQUEST HEADERS
$request_headers

# REQUEST BODY
$request_body

# SESSION
$all_session_variables
END;

        try {
            $mailclient = new \candb\utility\SendgridClient($sendgrid_api_key);
            $mailclient->sendmail($errors_mailfrom, $errors_mailto,
                'ProtoDB Application Error [' . getenv("PROTODB_INSTANCE_NAME") . ']', $body);
        }
        catch (\Throwable $new_exception) {
            error_log("ProtoDB: Failed to send error report: " . $new_exception->getMessage());
        }
    }

    if ($show_errors) {
        require_once "bsod.php";
        die();
    }
    else {
        $controller = new \candb\controller\BaseController();
        $view = [
            'heading' => 'Something went wrong.',
            'body' => 'The error has been logged and the FBI notified. Sorry for the inconvenience.',
        ];

        require_once 'views/error.php';
        die();
    }
}
