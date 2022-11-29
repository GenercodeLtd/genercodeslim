<?php
namespace GenerCodeSlim;

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use \Illuminate\Container\Container;

class UserMiddleware {
   
    private Container $app;

    function __construct(Container $app) {
        $this->app = $app;
    }
 

    public function __invoke(Request $request, RequestHandlerInterface $handler) : Response {

        $provider = $this->app->get("gcprovider");
        $provider->boot();
        return $handler->handle($request);
    }
}