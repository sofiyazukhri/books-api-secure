<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class Cors implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
        } else {
            $response = $handler->handle($request);
        }

        // 🚀 UPDATED: Added production preview ports (4173) to the whitelist
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigins = [
            'http://localhost:5173', 
            'http://127.0.0.1:5173',
            'http://localhost:4173', 
            'http://127.0.0.1:4173'
        ];
        
        $allowOrigin = in_array($origin, $allowedOrigins) ? $origin : 'http://localhost:5173';

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}