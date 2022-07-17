<?php

namespace BBLDN\LaravelDatabaseHttpProxy;

use Illuminate\Database\Connectors\ConnectorInterface;

class Connector implements ConnectorInterface
{
    /**
     * @param array $config
     * @return PDO
     */
    public function connect(array $config): PDO
    {
        return new PDO;
    }
}