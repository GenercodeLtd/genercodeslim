<?php

namespace GenerCodeSlim\Middleware;


class LaravelPsr15Bridge
{
    protected $middleware;

    function __construct($middleware) {
        $this->middleware = $middleware;
    }

    
    public function __invoke($request, RequestHandlerInterface $handler)
    {
        return $this->middleware->handle($request, $handler->handle);
    }
}
