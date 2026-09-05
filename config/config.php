<?php
return [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: 'neon_royale',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'app_key' => getenv('APP_KEY') ?: 'change-this-to-a-long-random-secret-key',
    'app_name' => 'Neon Royale',
];
