<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/provider.php';
start_secure_session();
send_security_headers();
