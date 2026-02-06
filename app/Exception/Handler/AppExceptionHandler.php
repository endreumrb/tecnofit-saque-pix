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
use App\Exception\InvalidScheduleException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function Hyperf\Support\env;

class AppExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $status = 500;
        $error = 'erro_interno';
        $message = $throwable->getMessage();

        if ($throwable instanceof AccountNotFoundException) {
            $status = 404;
            $error = 'conta_nao_encontrada';
        }

        if (
            $throwable instanceof InsufficientBalanceException
            || $throwable instanceof InvalidScheduleException
        ) {
            $status = 422;
            $error = 'erro_negocio';
        }

        $body = [
            'error' => $error,
            'message' => $status === 500 ? 'Erro Interno do Servidor' : $message,
        ];

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new SwooleStream(json_encode($body, JSON_PRETTY_PRINT)));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}
