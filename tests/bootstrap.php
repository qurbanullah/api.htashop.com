<?php

if (!extension_loaded('pdo_sqlite') || !extension_loaded('sqlite3')) {
    fwrite(STDERR, "SQLite extensions are required for tests. Refusing to run tests without pdo_sqlite and sqlite3.\n");
    exit(1);
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=true');
putenv('APP_KEY=base64:7M5J1KpA1Qq2y4gD0i7vQ8xN9rT6uW3sY2bC4dE6fG8=');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('CACHE_STORE=array');
putenv('MAIL_MAILER=array');
putenv('QUEUE_CONNECTION=sync');
putenv('SESSION_DRIVER=array');
putenv('PULSE_ENABLED=false');
putenv('TELESCOPE_ENABLED=false');
putenv('NIGHTWATCH_ENABLED=false');

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['APP_KEY'] = 'base64:7M5J1KpA1Qq2y4gD0i7vQ8xN9rT6uW3sY2bC4dE6fG8=';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';
$_ENV['CACHE_STORE'] = 'array';
$_ENV['MAIL_MAILER'] = 'array';
$_ENV['QUEUE_CONNECTION'] = 'sync';
$_ENV['SESSION_DRIVER'] = 'array';
$_ENV['PULSE_ENABLED'] = 'false';
$_ENV['TELESCOPE_ENABLED'] = 'false';
$_ENV['NIGHTWATCH_ENABLED'] = 'false';

$_SERVER['APP_ENV'] = 'testing';
$_SERVER['APP_DEBUG'] = 'true';
$_SERVER['APP_KEY'] = 'base64:7M5J1KpA1Qq2y4gD0i7vQ8xN9rT6uW3sY2bC4dE6fG8=';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = ':memory:';
$_SERVER['CACHE_STORE'] = 'array';
$_SERVER['MAIL_MAILER'] = 'array';
$_SERVER['QUEUE_CONNECTION'] = 'sync';
$_SERVER['SESSION_DRIVER'] = 'array';
$_SERVER['PULSE_ENABLED'] = 'false';
$_SERVER['TELESCOPE_ENABLED'] = 'false';
$_SERVER['NIGHTWATCH_ENABLED'] = 'false';

require __DIR__ . '/../vendor/autoload.php';
