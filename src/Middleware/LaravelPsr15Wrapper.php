<?php

namespace GenerCodeSlim\Middleware;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;


class LaravelPsr15Wrapper
{
    protected $app;
    protected $middleware;

    function __construct($app, $middleware) {
        $this->app = $app;
        $this->middleware = $middleware;
    }

    public function convertSymfonyRequest($request) {

    }

    
    public function __invoke($request, $handler)
    {
        $httpFoundationFactory = new \GenerCodeSlim\GenerCodeSymfonyBridge();
        $http_request = $httpFoundationFactory->createRequest($request);

        $this->app->instance("request", $http_request);

        $psr17Factory = new Psr17Factory();
        $response = $this->middleware->handle($http_request, function($request) use ($handler, $psr17Factory, $httpFoundationFactory) {
            $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
            return $httpFoundationFactory->createResponse($handler->handle($psrHttpFactory->createRequest($request)));
        });

        
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
        $psrResponse = $psrHttpFactory->createResponse($response);
        return $psrResponse;
    }
}
