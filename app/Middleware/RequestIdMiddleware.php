<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $requestId = $request->getHeaderLine('X-Request-ID');
        
        if (empty($requestId)) {
            $requestId = Uuid::uuid4()->toString();
        }
        
        Context::set('request_id', $requestId);
        
        $response = $handler->handle($request);
        
        return $response->withHeader('X-Request-ID', $requestId);
    }
}