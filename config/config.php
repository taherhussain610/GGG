<?php

declare(strict_types=1);

function config_env(string $name, string $default = ''): string
{
    $value = getenv($name);

    return $value === false ? $default : $value;
}

define('DB_HOST', config_env('DB_HOST', 'localhost'));
define('DB_PORT', config_env('DB_PORT', '3306'));
define('DB_NAME', config_env('DB_NAME', 'neon_royale'));
define('DB_USER', config_env('DB_USER', ''));
define('DB_PASS', config_env('DB_PASS', ''));
define('APP_KEY', config_env('APP_KEY', 'change-this-development-key-before-launch'));
define('APP_URL', rtrim(config_env('APP_URL', ''), '/'));
define('APP_ENV', config_env('APP_ENV', 'development'));

date_default_timezone_set('UTC');
