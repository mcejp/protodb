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

final class HttpResult
{
    const OK_CODE =             200;
    const FOUND_CODE =          302;
    const BAD_REQUEST_CODE =    400;
    const UNAUTHORIZED_CODE =   401;
    const FORBIDDEN_CODE =      403;
    const NOT_FOUND_CODE =      404;

    public $headers;
    public $body;

    /** @var int */ public $response_code;

    public function __construct(array $headers, ?string $body, int $response_code = self::OK_CODE)
    {
        $this->headers = $headers;
        $this->body = $body;
        $this->response_code = $response_code;
    }

    public static function make_redirect(string $location, int $code = self::FOUND_CODE): HttpResult {
        return new HttpResult(['Location' => $location], null, $code);
    }

    public static function with_response_code(int $code): HttpResult {
        return new HttpResult([], null, $code);
    }
}
