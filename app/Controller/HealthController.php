<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller;

use Hyperf\DbConnection\Db;
use Hyperf\Redis\Redis;
use Hyperf\Swagger\Annotation as SA;
use Psr\Log\LoggerInterface;
use Throwable;

#[SA\HyperfServer('http')]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly Redis $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    #[SA\Get(
        path: '/health',
        summary: 'Health check básico',
        tags: ['Monitoramento']
    )]
    #[SA\Response(
        response: 200,
        description: 'Serviço funcionando'
    )]
    public function basic(): array
    {
        return [
            'status' => 'ok',
            'service' => 'tecnofit-saque-pix',
            'timestamp' => date('c'),
        ];
    }

    #[SA\Get(
        path: '/health/ready',
        summary: 'Readiness probe',
        tags: ['Monitoramento']
    )]
    #[SA\Response(
        response: 200,
        description: 'Verifica se está pronto para receber tráfego'
    )]
    public function readiness(): array
    {
        $checks = [];
        $startTime = microtime(true);

        try {
            Db::select('SELECT 1');
            $checks['database'] = [
                'status' => 'healthy',
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (Throwable $e) {
            $checks['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];

            $this->logger->error('Database health check failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $redisStart = microtime(true);
        try {
            $this->redis->ping();
            $checks['redis'] = [
                'status' => 'healthy',
                'response_time_ms' => round((microtime(true) - $redisStart) * 1000, 2),
            ];
        } catch (Throwable $e) {
            $checks['redis'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];

            $this->logger->error('Redis health check failed', [
                'error' => $e->getMessage(),
            ]);
        }

        $allHealthy = ! in_array('unhealthy', array_column($checks, 'status'));

        return [
            'status' => $allHealthy ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => date('c'),
        ];
    }

    #[SA\Get(
        path: '/health/live',
        summary: 'Liveness probe',
        tags: ['Monitoramento']
    )]
    #[SA\Response(
        response: 200,
        description: 'Verifica se o processo está vivo'
    )]
    public function liveness(): array
    {
        return [
            'status' => 'alive',
            'timestamp' => date('c'),
        ];
    }
}
