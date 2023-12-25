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

namespace candb;

class PluginError extends \Exception
{
    /** @var string */ private $short_message;
    /** @var string */ private $stderr;

    public function __construct(string $short_message, string $stderr, $code = 0, \Exception $previous = null) {
        parent::__construct($short_message . "\n\n" . $stderr, $code, $previous);

        $this->short_message = $short_message;
        $this->stderr = $stderr;
    }

    public function getShortMessage(): string {
        return $this->short_message;
    }

    public function getStderr(): string {
        return $this->stderr;
    }
}
