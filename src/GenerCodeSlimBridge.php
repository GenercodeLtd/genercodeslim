<?php
namespace GenerCodeSlim;
use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Slim\App;
use \Slim\Factory\AppFactory;
use \Slim\Routing\RouteCollectorProxy;
use \Slim\Routing\RouteContext;
use \Slim\Exception\HttpException;
use \Illuminate\Support\Fluent;
use \Illuminate\Container\Container;
use Psr\Log\LoggerInterface;
use \GenerCodeOrm\Http\Controllers as Controllers;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Psr\Http\Message\ServerRequestInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;


class GenerCodeSlimBridge extends \Illuminate\Foundation\Http\Kernel
{

    protected $slim;
    protected $request;
    protected $middleware = [];

    public function __construct(Application $app, Router $router) {
        parent::__construct($app, $router);
        parent::bootstrap();
     
        AppFactory::setContainer($app);
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $this->slim = AppFactory::create($factory);

     
        $creator = new \Nyholm\Psr7Server\ServerRequestCreator(
            $factory, // ServerRequestFactory
            $factory, // UriFactory
            $factory, // UploadedFileFactory
            $factory  // StreamFactory
        );

        $this->request = $creator->fromGlobals();

        $converter = new GenerCodeConverter();

        $irequest = $converter->convertLaravelRequest($this->request);
        $app->instance("illu_request", $irequest);
    }


    public function __call($method, $args) {
        return call_user_func_array([$this->slim, $method], $args);
    }

    public function addMiddleware($middleware) {
        $this->middleware[] = $middleware;
    }


    public function initMiddleware()
    {
        $this->slim->addBodyParsingMiddleware();
        $this->slim->addRoutingMiddleware();

        foreach($this->middleware as $mware) {
            $this->slim->add($mware);
        }


        $container = $this->app;

        $this->slim->add(new Middleware\UserMiddleware(
            $container
        ));

        $this->slim->add(new Middleware\LaravelPsr15Wrapper($container, $container->make(\Illuminate\Session\Middleware\StartSession::class)));
        $this->slim->add(new Middleware\LaravelPsr15Wrapper($container, $container->make(\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class)));
        $this->slim->add(new Middleware\JsonContent());
      //  $this->app->add($container->make(\App\Http\Middleware\VerifyCsrfToken::class));
     
        $errorMiddleware = $this->slim->addErrorMiddleware(true, true, true);

        $app = $this->slim;
        $errFunc = function (
            Request $request,
            \Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails,
            ?LoggerInterface $logger = null
        ) use ($app) {
            $container = $app->getContainer();
            if ($logger) {
                $logger->error($exception->getMessage());
            }

            $payload = ['error' => $exception->getMessage(), "file"=>$exception->getFile(), "line"=>$exception->getLine(), "trace"=>$exception->getTrace()];

            $response = $app->getResponseFactory()->createResponse();
            $response->getBody()->write(
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            );

            try {
                $status = $exception->getCode();
            } catch(\Exception $e) {
                $status = 500;
            }

            $status = (int) $status;

            if ($status < 100 or $status > 500) {
                $status = 500;
            }

            return $response->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
        };
        $errorMiddleware->setDefaultErrorHandler($errFunc);

        //$this->slim->add(new Middleware\LaravelPsr15Wrapper($container, $container->make(\Fruitcake\Cors\HandleCors::class)));

        $this->slim->add(new Middleware\GenerCodeSlimCors(
            $container
        ));
    }


    public function addRoutes()
    {
        $this->slim->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });

        $this->slim->map(['POST', 'PUT', 'DELETE'], '/data/{model}', function (Request $request, Response $response, $args) {
            $modelController = $this->get(Controllers\ModelController::class);
            $method = $request->getMethod();
            if ($method == "POST") {
                $results = $modelController->create($args["model"], new Fluent($request->getParsedBody()));
            } elseif ($method == "PUT") {
                $results = $modelController->update($args["model"], new Fluent($request->getParsedBody()));
            } elseif ($method == "DELETE") {
                $results = $modelController->delete($args["model"], new Fluent($request->getParsedBody()));
            }
            $response->getBody()->write(json_encode($results));
            return $response;
        });


        $this->slim->post('/bulk/{model}', function (Request $request, Response $response, $args) {
            $modelController = $this->get(Controllers\ModelController::class);
            $results = $modelController->importFromCSV($args["model"], new Fluent($request->getParsedBody()));
            $response->getBody()->write(json_encode($results));
            return $response;
        });


        $this->slim->put('/data/{model}/resort', function (Request $request, Response $response, $args) {
            $modelController = $this->get(Controllers\ModelController::class);
            $results = $modelController->resort($args["model"], new Fluent($request->getParsedBody()));
            $response->getBody()->write(json_encode($results));
            return $response;
        });



        $this->slim->get('/data/{model}[/{state}]', function (Request $request, Response $response, $args) {
            $state = (isset($args["state"])) ? $args["state"] : "get";
            $repoController = $this->get(Controllers\RepositoryController::class);
            if ($state == "active") {
                $results = $repoController->getActive($args["model"], new Fluent($request->getQueryParams()));
                if (!$results) $results = "{}";
            } else if ($state == "first") {
                $arr = new Fluent($request->getQueryParams());
                $arr["__fields"] = ["--id"];
                $arr["__limit"] = 1;
                $results = $repoController->get($args["model"], $arr);
            } else {
                $results = $repoController->get($args["model"], new Fluent($request->getQueryParams()));
            }
            $response->getBody()->write(json_encode($results, JSON_INVALID_UTF8_SUBSTITUTE));
            return $response;
        });

        $this->slim->get('/check[/{model}/{id}]', function(Request $request, Response $response, $args) {
            $model = (isset($args["model"])) ? $args["model"] : null;
            $parent_id = (isset($args["id"])) ? $args["id"] : 0;
            $validator = new Controllers\DataController();
            $checks = $validator->validate($model, $parent_id);
            $response->getBody()->write(json_encode($checks));
            return $response;
        });


        $this->slim->get('/count/{model}', function (Request $request, Response $response, $args) {
            $name = $args['model'];
            $repoController = $this->get(Controllers\RepositoryController::class);
            $results = $repoController->count($args["model"], new Fluent($request->getQueryParams()));
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->slim->get("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(Controllers\AssetController::class);
            $data = $assetController->getAsset($args["model"], $args["field"], (int) $args["id"]);
            $response->getBody()->write($data["data"]);
            return $response->withHeader("Content-Type", $data["type"]);
        });

        $this->slim->get("/asset/exists/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(Controllers\AssetController::class);
            if (!$assetController->exists($args["model"], $args["field"], (int) $args["id"])) {
                throw new \Exception("Asset doesn't exist");
            }
            return $response;
        });


        $this->slim->post("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(Controllers\AssetController::class);
            $data = $assetController->patchAsset($args["model"], $args["field"], (int) $args["id"]);
            $response->getBody()->write(json_encode($data));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->slim->delete("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(Controllers\AssetController::class);
            $data = $assetController->removeAsset($args["model"], $args["field"], (int) $args["id"]);
            $response->getBody()->write(json_encode($data));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->slim->get("/reference/{model}/{field}[/{id}]", function ($request, $response, $args) {
            $refController = $this->get(Controllers\ReferenceController::class);
            $params = new Fluent($request->getQueryParams());
            $id = (isset($args["id"])) ? $args["id"] : 0;
            $results = $refController->load($args["model"], $args["field"], $id, $params);
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });



        $this->slim->group("/audit", function(RouteCollectorProxy $group) {
          
            $group->get("/history/{name}/{id}", function($request, $response, $args) {
                $params = $request->getQueryParams();
                $audit = $this->get(Controllers\AuditController::class);
                $obj = $audit->getObjectAt($args["name"], $args["id"], $params["date"]);
                $response->getBody()->write(json_encode($obj));
                return $response;
            });


            $group->get("/has-history/{name}/{id}", function($request, $response, $args) {
                $params = $request->getQueryParams();
                $audit = $this->get(Controllers\AuditController::class);
                $audit->hasChangeSince($args["name"], $args["id"], $params["date"]);
                $response->getBody()->write(json_encode($obj));
                return $response;
            });

            $group->get("/{name}/{id}", function($request, $response, $args) {
                $audit = $this->get(Controllers\AuditController::class);
                $response->getBody()->write(json_encode($audit->getAll($args["name"], $args["id"])));
                return $response;
            });

            $group->get("/deleted-since/{name}", function($request, $response, $args) {
                $audit = $this->get(Controllers\AuditController::class);
                $params = $request->getQueryParams();
                $response->getBody()->write(json_encode($audit->getAllDeletedSince($args["name"], $params["date"])));
            });

        });

        $this->slim->group("/reports", function(RouteCollectorProxy $group) {

            $group->get("/{name}", function($request, $response, $args) {
                $report = $this->get(Controllers\ReportsController::class);
                $data = $report->get($args["name"], new Fluent($request->getQueryParams()));
                $response->getBody()->write(json_encode($data));
                return $response;
            });


            $group->get("/{name}/{field}/{agg}", function($request, $response, $args) {
                $report = $this->get(Controllers\ReportsController::class);
                $data = $report->getAggregate($args["name"], $args["field"], $args["agg"], new Fluent($request->getQueryParams()));
                $response->getBody()->write(json_encode($data));
                return $response;
            });

        });


        $this->slim->group("/user", function (RouteCollectorProxy $group) {
            $group->get("/dictionary", function ($request, $response, $args) {
                $profile = $this->get("profile");
                $dict = file_get_contents($this->basePath() . "/api/Dictionary/" . $profile->name . ".json");
                $response->getBody()->write($dict);
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            $group->get("/details", function ($request, $response) {
                $profileController = $this->get(Controllers\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->userDetails()));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            $group->get("/site-map", function ($request, $response) {
                $profileController = $this->get(Controllers\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->getSitemap()));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            $group->post('/login/{name}', function (Request $request, Response $response, $args) {
                $converter = $this->make(GenerCodeConverter::class);
                
                $profileController = $this->get(Controllers\ProfileController::class);
                $response = $profileController->login($converter->convertLaravelRequest($request), $converter->convertLaravelResponse($response), $args["name"]);
                return $converter->convertPsrResponse($response);
            });

            $group->post('/register/{name}', function(Request $request, Response $response, $args) {
                $profileController = $this->get(Controllers\ProfileController::class);
                $obj = $profileController->create($args["name"], $request->getParsedBody());
                $response->getBody()->write(json_encode($obj));
                return $response;
            });

            $group->post('/login/token/{name}', function (Request $request, Response $response, $args) {
                $params = new Fluent($request->getParsedBody());
                $tokenHandler = $this->make(TokenHandler::class);
                return $tokenHandler->loginFromToken($params["token"], $response, $args["name"])
                ->withHeader('Content-Type', 'application/json');
            });


            $group->post('/anon/{name}', function (Request $request, Response $response, $args) {
                $name = $args['name'];
                $profileController = $this->get(Controllers\ProfileController::class);
                $id = $profileController->createAnon($args["name"]);
                $tokenHandler = $this->make(TokenHandler::class);
                $response = $tokenHandler->save($response, $name, $id);
                $response->getBody()->write(json_encode(["--id"=>$id]));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });


            $group->put("/switch-tokens", function (Request $request, Response $response, $args) {
                $tokenHandler = $this->get(TokenHandler::class);
                $response = $tokenHandler->switchTokens($request, $response);
                $response->getBody()->write(json_encode("success"));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            $group->get("/check-user", function (Request $request, Response $response, $args) {
                $profileController = $this->get(Controllers\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->checkUser()));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });


            $group->post("/logout", function (Request $request, Response $response, $args) {
                $converter = $this->make(GenerCodeConverter::class);
                $profileController = $this->get(Controllers\ProfileController::class);
                $response = $profileController->logout($converter->convertLaravelRequest($request), $converter->convertLaravelResponse($response));
                return $converter->convertPsrResponse($response);
            });
        });


        $this->slim->group("/dispatch", function (RouteCollectorProxy $group) {
            $group->get("/status/{id}", function (Request $request, Response $response, $args) {
                $repoController = $this->get(Controllers\QueueController::class);
                $data = $repoController->status($args["id"]);
                $response->getBody()->write(json_encode($data));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });


            $group->delete("/remove/{id}", function (Request $request, Response $response, $args) {
                $modelController = $this->get(Controllers\QueueController::class);
                $results = $modelController->delete($args["id"]);
                $response->getBody()->write(json_encode($results));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });
        });
    }


    function run() {
        $this->slim->run($this->request);
    }
}