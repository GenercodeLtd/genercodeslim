<?php
namespace GenerCodeSlim;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class GenerCodeConverter {

    protected $http_foundation;
    protected $psr_http;

    function __construct() {
        $this->http_foundation = new \GenerCodeSlim\GenerCodeSymfonyBridge();
        $psr17Factory = new Psr17Factory();
        $this->psr_http = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    }

    
    public function convertLaravelRequest($request) {
        return $this->http_foundation->createRequest($response);
    }


    public function convertLaravelResponse($request) {
        return $this->http_foundation->createResponse($response);
    }


    public function convertToPsrRequest($request) {
        return $this->psr_http->createRequest($request);
    }


    public function convertToPsrResponse($response) {
        return $this->psr_http->createResponse($response);
    }
}