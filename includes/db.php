<?php

declare(strict_types=1);

function app_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/../config/config.php';
    }

    return $config;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    if ($config['db_host'] === '' || $config['db_name'] === '' || $config['db_user'] === '') {
        throw new RuntimeException('Database settings are incomplete.');
    }
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function db_available(): bool
{
    try {
        db();
        return true;
    } catch (Throwable) {
        return false;
    }
}
