<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('sqlite3')) {
            throw new RuntimeException('SQLite extensions are required for tests. Refusing to run test suite without pdo_sqlite and sqlite3.');
        }

        // Force testing DB to in-memory sqlite to avoid accidental writes to real DB
        putenv('APP_ENV=testing');
        putenv('APP_DEBUG=true');
        putenv('APP_KEY=base64:7M5J1KpA1Qq2y4gD0i7vQ8xN9rT6uW3sY2bC4dE6fG8=');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        putenv('CACHE_STORE=array');
        putenv('MAIL_MAILER=array');
        putenv('QUEUE_CONNECTION=sync');
        putenv('SESSION_DRIVER=array');
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['APP_KEY'] = 'base64:7M5J1KpA1Qq2y4gD0i7vQ8xN9rT6uW3sY2bC4dE6fG8=';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['CACHE_STORE'] = 'array';
        $_ENV['MAIL_MAILER'] = 'array';
        $_ENV['QUEUE_CONNECTION'] = 'sync';
        $_ENV['SESSION_DRIVER'] = 'array';
        $_SERVER['APP_ENV'] = 'testing';
        $_SERVER['APP_DEBUG'] = 'true';
        $_SERVER['APP_KEY'] = 'base64:7M5J1KpA1Qq2y4gD0i7vQ8xN9rT6uW3sY2bC4dE6fG8=';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        $_SERVER['CACHE_STORE'] = 'array';
        $_SERVER['MAIL_MAILER'] = 'array';
        $_SERVER['QUEUE_CONNECTION'] = 'sync';
        $_SERVER['SESSION_DRIVER'] = 'array';

        // Load the application
        $app = require __DIR__ . '/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Ensure config reflects in-memory sqlite
        try {
            // If config() helper is available
            config(['app.env' => 'testing']);
            config(['database.default' => 'sqlite']);
            config(['database.connections.sqlite.database' => ':memory:']);
            config(['database.connections.sqlite.foreign_key_constraints' => true]);
            // Poison non-test SQL connections so a drift cannot hit MariaDB.
            config(['database.connections.mariadb.host' => 'invalid.test']);
            config(['database.connections.mariadb.port' => 65535]);
            config(['database.connections.mariadb.database' => '__tests_must_use_sqlite__']);
            config(['database.connections.mariadb.username' => '__forbidden__']);
            config(['database.connections.mariadb.password' => '__forbidden__']);
            config(['database.connections.mysql.host' => 'invalid.test']);
            config(['database.connections.mysql.port' => 65535]);
            config(['database.connections.mysql.database' => '__tests_must_use_sqlite__']);
            config(['database.connections.mysql.username' => '__forbidden__']);
            config(['database.connections.mysql.password' => '__forbidden__']);
        } catch (\Throwable $e) {
            // ignore; bootstrap will still pick up env vars
        }

        if ($app['config']->get('database.default') !== 'sqlite') {
            throw new RuntimeException('Unsafe test configuration detected: database.default is not sqlite. Refusing to run tests.');
        }

        if ($app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Unsafe test configuration detected: sqlite test database is not :memory:. Refusing to run tests.');
        }

        return $app;
    }
}
