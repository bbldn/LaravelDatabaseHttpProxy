<?php

namespace BBLDN\LaravelDatabaseHttpProxy;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider as Base;
use BBLDN\LaravelDatabaseHttpProxy\Connector as HTTPConnector;
use BBLDN\LaravelDatabaseHttpProxy\Connection as HTTPConnection;

class ServiceProvider extends Base
{
    /**
     * @return void
     */
    public function boot(): void
    {
        $this->app->singleton('db.connector.http', HTTPConnector::class);
        $this->app->singleton('db.connector.https', HTTPConnector::class);

        $callback = static fn($c, $d, $p, $config) => new HTTPConnection($c, $d, $p, $config);

        Connection::resolverFor('http', $callback);
        Connection::resolverFor('https', $callback);
    }
}