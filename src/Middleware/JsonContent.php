<?php

namespace GenerCodeSlim\Middleware;

class JsonContent
{
       
    public function __invoke($request, $handler)
    {
        $response = $handler->handle($request);
        if (!$response->hasHeader("Content-Type")) {
            $response = $response->withHeader('Content-Type', 'application/json');
        }
        return $response;
    }
}
