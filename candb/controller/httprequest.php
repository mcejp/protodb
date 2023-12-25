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

final class HttpRequest
{
    /** @var array */ public $headers;
    /** @var string|null */ public $body;

    public function __construct(array $headers, ?string $body)
    {
        $this->headers = $headers;
        $this->body = $body;
    }

    /**
     * @return mixed decoded JSON if the request is non-empty and of application/json MIME type, otherwise null
     * @throws \JsonException if the request appears valid, but the payload does not validate
     */
    public function get_body_as_json() {
        if ($this->body === null) {
            return null;
        }

        $content_type = $this->get_header('Content-Type');

        if ($content_type === 'application/json') {
            return json_decode($this->body, JSON_THROW_ON_ERROR);
        }
        else {
            return null;
        }
    }

    public function get_header(string $name): ?string {
        if (array_key_exists($name, $this->headers)) {
            return $this->headers[$name];
        }
        else {
            return null;
        }
    }
}
