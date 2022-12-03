<?php
namespace GenerCodeSlim;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;


class GenerCodeSymfonyBridge extends HttpFoundationFactory {

   
    public function createRequest(ServerRequestInterface $psrRequest, bool $streamed = false)
    {
        $request = parent::createRequest($psrRequest, $streamed);
        return new Request($request);
    }



    /**
     * {@inheritdoc}
     */
    public function createResponse(ResponseInterface $psrResponse, bool $streamed = false)
    {
        $response = parent::createResponse($psrResponse, $streamed);
        return new Response($response);
    }

}
