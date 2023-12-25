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

namespace candb\utility;

class SendgridClient {
    const ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    /** @var string */ private $api_key;

    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    public function sendmail(string $from, string $to, string $subject, string $body): void
    {
        $mail = [
            "personalizations" => [[        // this needs to be an array of 1 object!
                "to" => [
                    ["email" => $to],
                ],
                "subject" => $subject,
            ]],
            "from" => [
                "email" => $from,
            ],
            "content" => [
                ["type" => "text/plain", "value" => $body],
            ]
        ];

        $this->json_request($mail);
    }

    private function json_request(array $data): void
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);      // required to suppress output!
        curl_setopt($curl, CURLOPT_URL, self::ENDPOINT);

        $result = curl_exec($curl);

        $httpcode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpcode < 200 || $httpcode > 299)
            throw new \Exception('SendgridClient HTTP ' . $httpcode . ' BODY ' . $result);

        if ($result === FALSE)
            die('SendgridClient ERROR ' . curl_error($curl));

        curl_close($curl);
    }
}
