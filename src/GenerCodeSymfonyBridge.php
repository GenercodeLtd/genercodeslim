<?php
namespace GenerCodeSlim;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;


class GenerCodeSymfonyBridge extends HttpFoundationFactory {

   
    public function createRequest(ServerRequestInterface $psrRequest, bool $streamed = false)
    {
        $request = parent::createRequest($psrRequest, $streamed);

        //__construct(array $query = [], array $request = [], array $attributes = [], array $cookies = [], array $files = [], array $server = [], $content = null)
        
        $files = array_filter($request->files->all());
        $irequest = new Request();
        $irequest->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $files,
            $request->server->all(),
            $request->getContent()
        );

        return $irequest;
    }


    public function createResponse(ResponseInterface $psrResponse, bool $streamed = false)
    {
        $response = parent::createResponse($psrResponse, $streamed);
        return new Response($response->getContent(), $response->getStatusCode(), $response->headers);
    }

}
