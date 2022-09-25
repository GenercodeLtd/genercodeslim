<?php
namespace GenerCodeSlim;

use \Dflydev\FigCookies\FigRequestCookies;
use \Dflydev\FigCookies\FigResponseCookies;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use \Slim\Exception\HttpUnauthorizedException;

class UserMiddleware {
   
    private \GenerCodeOrm\Factory $factory;
    private TokenHandler $handler;
    private $container;

    function __construct(\Illuminate\Container\Container $container, \GenerCodeOrm\Factory $factory, TokenHandler $handler) {
        $this->factory = $factory;
        $this->handler = $handler;
        $this->container = $container;
    }
 

    function setConfigs($configs) {
        $this->handler->setConfigs($configs);
    }


    public function __invoke(Request $request, RequestHandlerInterface $handler) : Response {

        $payload = new \StdClass;
        $payload->u = "public";
        $payload->i = 0;
        
        $auth = FigRequestCookies::get($request, "api-auth");
        $expired = false;
        //otherwise check if set via cookie
        if ($auth and $auth->getValue()) {
            try {
                $payload = $this->handler->decode($auth->getValue());
            } catch(\Exception $e) {
                $expired = true;
            }
        }
        
        $profile = ($this->factory)($payload->u);
        $profile->id = $payload->i;
        $this->container->instance(\GenerCodeOrm\Profile::class, $profile);
                 
            //get the user
           // $factory = new \PressToJam\ProfileFactory(); 
        $response = $handler->handle($request);
        if ($expired) return $this->handler->logout($response);
        else return $response;
    }
}