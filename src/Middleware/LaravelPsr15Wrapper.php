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
        $converter = $this->app->make(\GenerCodeSlim\GenerCodeConverter::class);
        $http_request = $converter->convertLaravelRequest($request);

        $this->app->instance("request", $http_request);

        $response = $this->middleware->handle($http_request, function($request) use ($handler, $converter) {
            return $converter->convertLaravelResponse(
                $handler->handle(
                    $converter->convertPsrRequest($request)
                )
            );
        });

    
        return $converter->convertPsrReponse($response);
    }
}
