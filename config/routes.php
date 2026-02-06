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
use App\Controller\AccountController;
use App\Controller\HealthController;
use Hyperf\HttpServer\Router\Router;

Router::get('/health', [HealthController::class, 'basic']);
Router::get('/health/ready', [HealthController::class, 'readiness']);
Router::get('/health/live', [HealthController::class, 'liveness']);

Router::post(
    '/account/{accountId}/balance/withdraw',
    [AccountController::class, 'withdraw']
);
