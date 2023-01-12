<?php
namespace GenerCodeSlim\Middleware;

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Slim\App;
use \Slim\Factory\AppFactory;
use \Slim\Routing\RouteCollectorProxy;
use \Slim\Routing\RouteContext;
use \Slim\Exception\HttpException;
use \Illuminate\Support\Fluent;
use \Illuminate\Container\Container;
use \Illuminate\Database\Connectors\ConnectionFactory;
use \Illuminate\Database\DatabaseManager;
use \Illuminate\FileSystem\FileSystemManager;
use Psr\Log\LoggerInterface;
use Psr\Http\Server\RequestHandlerInterface;

class GenerCodeSlimCors
{

    protected $app;

    function __construct(Container $app) {
        $this->app = $app;
    }



    public function __invoke(Request $request, RequestHandlerInterface $handler) : Response {
        $origin = 0;
        
        if (isset($_SERVER['HTTP_ORIGIN']) AND $_SERVER['HTTP_ORIGIN']) $origin = $_SERVER['HTTP_ORIGIN'];
        else if (isset($_SERVER['HTTP_REFERER'])) $origin = $_SERVER['HTTP_REFERER'];
        
        $response = $handler->handle($request);
        return $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader("Access-Control-Allow-Headers", implode(", ", config("cors.headers")))
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
