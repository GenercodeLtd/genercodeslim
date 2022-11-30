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

        if (!$this->app->config->has("factory")) {
            throw new \PtjException("Factory needs to be set in the configs");
        }
        $factory_name = $this->app->config->get("factory");
        $factory = new $factory_name();

        $auth = $this->app->get("auth");
        $user = $auth->user();

        if (!$user) {
            $profile = ($factory)("public");
            $profile->id = 0;
        } else {
            $profile = ($factory)($user->type);
            $profile->id = $user->getAuthIdentifier();
        }

        $this->app->instance(Profile::class, $profile);
        $this->app->instance(Factory::class, $profile->factory);
        return $handler->handle($request);
    }
}