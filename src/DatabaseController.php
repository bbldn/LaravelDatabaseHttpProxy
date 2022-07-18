<?php

namespace BBLDN\LaravelDatabaseHttpProxy;

use Throwable;
use BadMethodCallException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller as Base;
use Illuminate\Config\Repository as ConfigRepository;

class DatabaseController extends Base
{
    private ConfigRepository $configRepository;

    /**
     * @param ConfigRepository $configRepository
     */
    public function __construct(ConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * @param string $method
     * @param array $params
     * @return array
     */
    private function runMethod(string $method, array $params): array
    {
        $connection = DB::connection($this->configRepository->get('databasehttpproxy.connection'));

        $result = match ($method) {
            'delete' => call_user_func_array([$connection, 'delete'], $params),
            'insert' => call_user_func_array([$connection, 'insert'], $params),
            'select' => call_user_func_array([$connection, 'select'], $params),
            'update' => call_user_func_array([$connection, 'update'], $params),
            'getDatabaseName' => call_user_func([$connection, 'getDatabaseName']),
            'selectOne' => call_user_func_array([$connection, 'selectOne'], $params),
            'statement' => call_user_func_array([$connection, 'statement'], $params),
            'unprepared' => call_user_func_array([$connection, 'unprepared'], $params),
            'affectingStatement' => call_user_func_array([$connection, 'affectingStatement'], $params),
            default => throw new BadMethodCallException("Method: \"$method\" does not exist."),
        };

        return [$result, $connection->getPdo()->lastInsertId()];
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $token = $this->configRepository->get('databasehttpproxy.token');
        if (null !== $token) {
            $givenToken = str_replace('Bearer ', '', $request->header('Authorization'));
            if ($token !== $givenToken) {
                return new JsonResponse([
                    'data' => null,
                    'error' => [
                        'name' => 'AuthorizationException',
                        'message' => 'Bad authorization token',
                    ],
                ]);
            }
        }

        $array = json_decode((string)$request->getContent(), true);

        $data = null;
        $error = null;
        $lastInsertId = false;
        try {
            [$data, $lastInsertId] = $this->runMethod($array['method'] ?? '', $array['params'] ?? []);
        } catch (Throwable $e) {
            $error = ['name' => get_class($e), 'message' => $e->getMessage()];
        }

        return new JsonResponse(['data' => $data, 'error' => $error, 'lastInsertId' => $lastInsertId]);
    }
}