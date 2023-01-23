<?php

namespace GenerCodeSlim\Middleware;

class JsonContent
{
       
    public function __invoke($request, $handler)
    {
        $response = $handler->handle($request);
        if (!$response->hasHeader("Content-Type")) {
            $response->withHeader('Content-Type', 'application/json');
        }
        return $response;
    }
}
