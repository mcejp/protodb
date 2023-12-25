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

namespace candb\service;

final class PythonInvokerResult
{
    public int $status;
    public string $stdout;
    public string $stderr;

    public function __construct(int $status, string $stdout, string $stderr)
    {
        $this->status = $status;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }
}

final class PythonInvoker
{
    private const TIMEOUT_SECONDS = 10;

    /**
     * @param string[] $command_line
     * @param string|null $workdir
     * @param string[]|null $extra_env
     * @param string|null $stdin
     * @return PythonInvokerResult
     */
    public function call(array $command_line, ?string $workdir = null, ?array $extra_env = null,
                         ?string $stdin = null): PythonInvokerResult
    {
        if ($extra_env === null) {
            $env = null;
        }
        else {
            $env = array_merge(getenv(), $extra_env);
        }

        return $this->subprocess_call(['python3', ...$command_line], $workdir, $env, $stdin);
    }

    /** @param string[] $command_line */
    private function subprocess_call(array $command_line, ?string $workdir, ?array $env,
                                     ?string $stdin): PythonInvokerResult
    {
        $descriptorspec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"], // stderr
        ];

        $process = proc_open($command_line, $descriptorspec, $pipes, $workdir, $env);
        assert(is_resource($process));

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }

        fclose($pipes[0]);

        stream_set_timeout($pipes[1], self::TIMEOUT_SECONDS);       // doesn't seem to actually work :(
                                                                            // therefore a stuck script will bring down the whole server
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        stream_set_timeout($pipes[2], self::TIMEOUT_SECONDS);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $status = proc_close($process);

        return new PythonInvokerResult($status, $stdout, $stderr);
    }
}
