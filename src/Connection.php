<?php

namespace BBLDN\LaravelDatabaseHttpProxy;

use Closure;
use Throwable;
use Generator;
use LogicException;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Connection as Base;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Database\Query\Processors\MySqlProcessor;
use Illuminate\Database\Query\Processors\SQLiteProcessor;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Database\Query\Processors\SqlServerProcessor;

class Connection extends Base
{
    private HttpClient $httpClient;

    /**
     * @param $pdo
     * @param string $database
     * @param string $tablePrefix
     * @param array $config
     */
    public function __construct($pdo, string $database = '', string $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);

        $this->httpClient = $this->createHttpClient($config);
        $this->queryGrammar = $this->createQueryGrammar($config);
        $this->postProcessor = $this->createPostProcessor($config);
    }

    /**
     * @param array $config
     * @return Grammar
     */
    private function createQueryGrammar(array $config): Grammar
    {
        return match ($config['proxy_driver'] ?? '') {
            'mysql' => new MySqlGrammar,
            'sqlite' => new SQLiteGrammar,
            'pgsql' => new PostgresGrammar,
            'sqlsrv' => new SqlServerGrammar,
            default => new Grammar,
        };
    }

    /**
     * @param array $config
     * @return Processor
     */
    private function createPostProcessor(array $config): Processor
    {
        return match ($config['proxy_driver'] ?? '') {
            'mysql' => new MySqlProcessor,
            'sqlite' => new SQLiteProcessor,
            'pgsql' => new PostgresProcessor,
            'sqlsrv' => new SqlServerProcessor,
            default => new Processor,
        };
    }

    /**
     * @param array $config
     * @return HttpClient
     */
    private function createHttpClient(array $config): HttpClient
    {
        $baseUri = '';
        if (true === key_exists('driver', $config)) {
            $baseUri .= $config['driver'] . '://';
        }

        if (true === key_exists('host', $config)) {
            $baseUri .= $config['host'];
        }

        if (true === key_exists('port', $config)) {
            $baseUri .= ':' . $config['port'];
        }

        if (true === key_exists('database', $config)) {
            $baseUri .= '/' . $config['database'];
        }

        $headers = [];
        if (true === key_exists('token', $config)) {
            $headers['Authorization'] = "Bearer {$config['token']}";
        }

        return new HttpClient(['base_uri' => $baseUri, 'headers' => $headers]);
    }

    /**
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws GuzzleException
     * @throws ConnectionException
     */
    private function request(string $method, array $params = []): mixed
    {
        $response = $this->httpClient->request('POST', '', [
            'json' =>  [
                'params' => $params,
                'method' => $method,
            ],
        ]);

        $json = json_decode((string)$response->getBody(), true);
        if (false === $json || null === $json) {
            throw new ConnectionException('Bad response');
        }

        $lastInsertId = $json['lastInsertId'] ?? null;
        if (true === key_exists('lastInsertId', $json)) {
            if (true === method_exists($this->pdo, 'setLastInsertId')) {
                $this->pdo->setLastInsertId($lastInsertId);
            }
        }

        $data = $json['data'] ?? null;
        if (null !== $data) {
            return $data;
        }

        $error = $json['error'] ?? [];

        throw new ConnectionException(($error['name'] ?? '') . ': ' . ($error['message'] ?? ''));
    }

    /**
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return mixed
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function selectOne($query, $bindings = [], $useReadPdo = true): mixed
    {
        return $this->request('selectOne', [$query, $bindings, $useReadPdo]);
    }

    /**
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return array
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        return $this->request('select', [$query, $bindings, $useReadPdo]);
    }

    /**
     * @param string $query
     * @param array $bindings
     * @return bool
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function insert($query, $bindings = []): bool
    {
        return (bool)$this->request('insert', [$query, $bindings]);
    }

    /**
     * @param string $query
     * @param array $bindings
     * @return int
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function update($query, $bindings = []): int
    {
        return (int)$this->request('update', [$query, $bindings]);
    }

    /**
     * @param string $query
     * @param array $bindings
     * @return int
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function delete($query, $bindings = []): int
    {
        return (int)$this->request('delete', [$query, $bindings]);
    }

    /**
     * @param string $query
     * @param array $bindings
     * @return bool
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function statement($query, $bindings = []): bool
    {
        return true === $this->request('statement', [$query, $bindings]);
    }

    /**
     * @param string $query
     * @param array $bindings
     * @return int
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function affectingStatement($query, $bindings = []): int
    {
        return (int)$this->request('affectingStatement', [$query, $bindings]);
    }

    /**
     * @param string $query
     * @return bool
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function unprepared($query): bool
    {
        return true === $this->request('unprepared', [$query]);
    }

    /**
     * @return string
     * @throws GuzzleException
     * @throws ConnectionException
     */
    public function getDatabaseName(): string
    {
        return (string)$this->request('getDatabaseName');
    }

    /**
     * @return MySqlGrammar
     */
    protected function getDefaultQueryGrammar(): MySqlGrammar
    {
        return new MySqlGrammar;
    }

    /**
     * @param string $query
     * @param array $bindings
     * @param bool $useReadPdo
     * @return Generator
     *
     * @noinspection PhpInconsistentReturnPointsInspection
     */
    public function cursor($query, $bindings = [], $useReadPdo = true): Generator
    {
        throw new LogicException('Unsupported operation');
    }

    /**
     * @param Closure  $callback
     * @param int $attempts
     * @return mixed
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback, $attempts = 1): mixed
    {
        throw new LogicException('Unsupported operation');
    }

    /**
     * @return void
     */
    public function beginTransaction(): void
    {
        throw new LogicException('Unsupported operation');
    }

    /**
     * @return void
     */
    public function commit(): void
    {
        throw new LogicException('Unsupported operation');
    }

    /**
     * @param  int|null  $toLevel
     * @return void
     *
     * @throws Throwable
     */
    public function rollBack($toLevel = null): void
    {
        throw new LogicException('Unsupported operation');
    }

    /**
     * @return int
     */
    public function transactionLevel(): int
    {
        throw new LogicException('Unsupported operation');
    }

    /**
     * @param Closure $callback
     * @return array
     */
    public function pretend(Closure $callback): array
    {
        throw new LogicException('Unsupported operation');
    }
}