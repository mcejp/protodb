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
?>
<!doctype html>

<html>
    <head>
        <style>
            html {
                font-size: 1.2em;
            }

            body {
                background: #0000aa;
                color: #aaaaaa;
                font-family: courier, monospace;
            }

            h1 {
                display: inline-block;
                padding: 0em 1ch;
                margin: 0 auto;
                font-size: 1rem;
                font-weight: normal;
                background: #aaaaaa;
                color: #0000aa;
            }

            p {
                margin: 2em 0;
                text-align: left;
            }

            a, a:hover {
                color: inherit;
                font: inherit;
            }

            blink {
                color: yellow;
                -webkit-animation: blink 1s steps(1, end) infinite;
                animation: blink 1s steps(1, end) infinite;
            }

            @-webkit-keyframes blink {
                80% {
                    opacity: 0;
                }
            }

            @keyframes blink {
                80% {
                    opacity: 0;
                }
            }
            ul {
                list-style: none;
            }
            ul li::before {
                content: '*';
                position: absolute;
                left: 1em;
            }

            .preformatted {
                white-space: pre;
            }

            .bg-dkgray { background-color: #555555; }
            .c-white { color: #ffffff; }
        </style>
    </head>

    <body>
        <!-- From http://www.catswhocode.com/blog/how-to-create-a-bsod-like-404-page -->

        <h1>ProtoDB <?= $app_version ?></h1>

        <p>Exception <span class="c-white"><?= htmlspecialchars(get_class($exception), ENT_QUOTES) ?></span> has occured. Message:</p>

        <p><span class="c-white preformatted"><?= htmlentities($exception->getMessage(), ENT_QUOTES) ?></span></p>
        <p>in <span class=""><?= $exception->getFile() . ':' . $exception->getLine() ?></span></p>

        <p>Stack trace:</p>
        <ul>
            <?php
            foreach (explode("\n", $exception->getTraceAsString()) as $line) {
                ?>
                <li><?= $line ?></li>
                <?php
            }
            ?>
        </ul>

        <p>
            Press any key to restart your computer <blink>_</blink>
        </p>

    </body>
</html>
