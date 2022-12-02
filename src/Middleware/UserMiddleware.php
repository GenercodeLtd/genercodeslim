<?php
namespace GenerCodeSlim\Middleware;

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

        if (!$this->app->config->has("factory")) {
            throw new \PtjException("Factory needs to be set in the configs");
        }
        $factory_name = $this->app->config->get("factory");
        $factory = new $factory_name();

        $irequest = $this->app["request"];

        $auth = $this->app->get("auth");
        $cookie = $irequest->cookie($auth->guard()->getName());


        if (!$cookie) {
            $profile = ($factory)("public");
            $profile->id = 0;
        } else {
            $user = json_decode($cookie);
            $profile = ($factory)($user->type);
            $profile->id = $user->id;
        }

        $this->app->instance("profile", $profile);
        return $handler->handle($request);
    }
}