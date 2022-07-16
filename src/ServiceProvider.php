<?php

namespace BBLDN\LaravelDatabaseHttpProxy;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider as Base;
use BBLDN\LaravelDatabaseHttpProxy\Connection as HTTPConnection;

class ServiceProvider extends Base
{
    /**
     * @return void
     */
    public function boot(): void
    {
        /** @noinspection PhpUndefinedFunctionInspection */
        $this->publishes([__DIR__ . '/../config/databasehttpproxy.php' => config_path('databasehttpproxy.php')]);

        $callback = static fn($c, $d, $p, $config) => new HTTPConnection($c, $d, $p, $config);

        Connection::resolverFor('http', $callback);
        Connection::resolverFor('https', $callback);
    }
}