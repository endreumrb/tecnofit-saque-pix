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

namespace App\Exception\Handler;

use App\Exception\AccountNotFoundException;
use App\Exception\InsufficientBalanceException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class BusinessExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();

        [$statusCode, $error, $message] = match (true) {
            $throwable instanceof AccountNotFoundException => [404, 'conta_nao_encontrada', $throwable->getMessage()],
            $throwable instanceof InsufficientBalanceException => [422, 'saldo_insuficiente', $throwable->getMessage()],
            $throwable instanceof InvalidArgumentException => [400, 'erro_validacao', $throwable->getMessage()],
            default => [500, 'erro_interno', 'Ocorreu um erro inesperado']
        };

        return $response
            ->withStatus($statusCode)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new SwooleStream(json_encode([
                'error' => $error,
                'message' => $message,
            ])));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof AccountNotFoundException
            || $throwable instanceof InsufficientBalanceException
            || $throwable instanceof InvalidArgumentException;
    }
}
