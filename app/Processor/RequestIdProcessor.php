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

namespace App\Processor;

use Hyperf\Context\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $requestId = Context::get('request_id');

        if ($requestId) {
            $record->extra['request_id'] = $requestId;
        }

        return $record;
    }
}
