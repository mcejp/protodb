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

final class TinySentryClient {
    private string $base_url;
    private string $public_key;

    private const STRIP_PREFIX = '/var/www/html/';

    public function __construct(string $sentry_dsn)
    {
        // DSN format per https://develop.sentry.dev/sdk/overview/:
        // {PROTOCOL}://{PUBLIC_KEY}@{HOST}/{PROJECT_ID}

        $matches = array();
        preg_match('/(\w+):\/\/(\w+)@([\w.]+)\/(\w+)/', $sentry_dsn, $matches);
        [$_, $protocol, $this->public_key, $host, $project_id] = $matches;

        $this->base_url = "$protocol://$host/api/$project_id/";
    }

    public function log_exception(\Throwable $exception, string $server_name, string $url, string $release, ?string $environment): void
    {
        $event = [
            'event_id' => self::make_event_id(),
            'timestamp' => time(),
            'platform' => 'php',
            'transaction' => $url,
            'server_name' => $server_name,
            'release' => $release,
            'environment' => $environment,
            'exception' => [
                'values' => [[
                    'type' => get_class($exception),
                    'value' => $exception->getMessage(),
                    'stacktrace' => self::make_backtrace($exception),
                ]],
            ]
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($event));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "X-Sentry-Auth: Sentry sentry_version=7,sentry_key=$this->public_key"
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT_MS, 5000);
        curl_setopt($curl, CURLOPT_URL, $this->base_url . 'store/');

        $result = curl_exec($curl);

        if ($result === FALSE)
            throw new \Exception('TinySentryClient ERROR ' . curl_error($curl));

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpcode !== 200)
            throw new \Exception('TinySentryClient HTTP ' . $httpcode);

        curl_close($curl);
    }

    private static function make_backtrace(\Throwable $exception): array
    {
        $frames = [];

        foreach (array_reverse($exception->getTrace()) as $trace_frame) {
            $frames[] = self::make_backtrace_frame($trace_frame);
        }

        $frames[] = self::make_backtrace_frame([
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);

        return ['frames' => $frames];
    }

    private static function make_backtrace_frame(array $trace_frame): array {
        $abs_path = $trace_frame['file'] ?? '[internal]';

        $frame = [
            'abs_path' => $trace_frame['file'] ?? null,
            'filename' => self::unprefix_local_path($abs_path),
            'lineno' => $trace_frame['line'] ?? 0,
            'function' => $trace_frame['function'] ?? null,
        ];

        if (isset($trace_frame['file']) && isset($trace_frame['line'])) {
            $maxContextLines = 5;
            $sourceCodeExcerpt = self::getSourceCodeExcerpt($maxContextLines, $trace_frame['file'], $trace_frame['line']);

            $frame['pre_context'] = $sourceCodeExcerpt['pre_context'];
            $frame['context_line'] = $sourceCodeExcerpt['context_line'];
            $frame['post_context'] = $sourceCodeExcerpt['post_context'];
        }

        return $frame;
    }

    private static function make_event_id(): string
    {
        return bin2hex(openssl_random_pseudo_bytes(16));
    }

    private static function unprefix_local_path(string $path): string
    {
        if (substr($path, 0, strlen(self::STRIP_PREFIX)) == self::STRIP_PREFIX) {
            $path = substr($path, strlen(self::STRIP_PREFIX));
        }

        return $path;
    }

    // Adapted from https://github.com/getsentry/sentry-php/blob/4f6f8fa701e5db53c04f471b139e7d4f85831f17/src/Integration/FrameContextifierIntegration.php

    /**
     * Gets an excerpt of the source code around a given line.
     *
     * @param int    $maxContextLines The maximum number of lines of code to read
     * @param string $filePath        The file path
     * @param int    $lineNumber      The line to centre about
     *
     * @return array<string, mixed>
     *
     * @psalm-return array{
     *     pre_context: string[],
     *     context_line: string|null,
     *     post_context: string[]
     * }
     */
    private static function getSourceCodeExcerpt(int $maxContextLines, string $filePath, int $lineNumber): array
    {
        $frame = [
            'pre_context' => [],
            'context_line' => null,
            'post_context' => [],
        ];

        $target = max(0, ($lineNumber - ($maxContextLines + 1)));
        $currentLineNumber = $target + 1;

        try {
            $file = new \SplFileObject($filePath);
            $file->seek($target);

            while (!$file->eof()) {
                /** @var string $line */
                $line = $file->current();
                $line = rtrim($line, "\r\n");

                if ($currentLineNumber === $lineNumber) {
                    $frame['context_line'] = $line;
                } elseif ($currentLineNumber < $lineNumber) {
                    $frame['pre_context'][] = $line;
                } elseif ($currentLineNumber > $lineNumber) {
                    $frame['post_context'][] = $line;
                }

                ++$currentLineNumber;

                if ($currentLineNumber > $lineNumber + $maxContextLines) {
                    break;
                }

                $file->next();
            }
        } catch (\Throwable $exception) {
        }

        return $frame;
    }
}
