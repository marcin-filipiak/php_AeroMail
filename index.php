<?php
/*
Copyright (C) [2026] [ Marcin Filipiak ]

https://github.com/marcin-filipiak/php_AeroMail

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
*/

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/core/Model.php';
require_once __DIR__ . '/app/core/View.php';
require_once __DIR__ . '/app/core/Controller.php';
require_once __DIR__ . '/app/core/App.php';
require_once __DIR__ . '/app/models/ImapModel.php';
require_once __DIR__ . '/app/models/SmtpModel.php';

$app = new App();
