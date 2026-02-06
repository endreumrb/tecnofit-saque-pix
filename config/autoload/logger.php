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
use App\Processor\RequestIdProcessor;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => [
        'handler' => [
            'class' => StreamHandler::class,
            'constructor' => [
                'stream' => 'php://stdout',
                'level' => Logger::DEBUG,
            ],
        ],
        'formatter' => [
            'class' => JsonFormatter::class,
            'constructor' => [
                'batchMode' => JsonFormatter::BATCH_MODE_NEWLINES,
                'appendNewline' => true,
            ],
        ],
        'processors' => [
            [
                'class' => PsrLogMessageProcessor::class,
            ],
            [
                'class' => ProcessIdProcessor::class,
            ],
            [
                'class' => RequestIdProcessor::class,
            ],
        ],
    ],
];
