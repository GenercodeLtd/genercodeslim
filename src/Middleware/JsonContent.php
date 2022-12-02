<?php

namespace GenerCodeSlim\Middleware;

class JsonContent
{
       
    public function __invoke($request, $handler)
    {
        $response = $handler->handle($request);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
