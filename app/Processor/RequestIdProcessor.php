<?php

declare(strict_types=1);

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