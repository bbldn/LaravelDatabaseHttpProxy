<?php

namespace BBLDN\LaravelDatabaseHttpProxy;

class PDO
{
    private mixed $lastInsertId = false;

    public function __construct()
    {
    }

    /**
     * @param mixed $name
     * @return mixed
     */
    public function lastInsertId($name = null): mixed
    {
        return $this->lastInsertId;
    }

    /**
     * @param mixed $lastInsertId
     * @return PDO
     */
    public function setLastInsertId(mixed $lastInsertId): self
    {
        $this->lastInsertId = $lastInsertId;

        return $this;
    }
}