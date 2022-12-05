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


class GenerCodeSlimBridge
{

    protected $app;
    protected $request;

    public function __construct() {
        $kernel = new GenerCodeKernel();
        $container = $kernel->buildContainer();

        AppFactory::setContainer($container);
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $this->app = AppFactory::create($factory);

        $container->instance(App::class, $this->app);

        $creator = new \Nyholm\Psr7Server\ServerRequestCreator(
            $factory, // ServerRequestFactory
            $factory, // UriFactory
            $factory, // UploadedFileFactory
            $factory  // StreamFactory
        );

        $this->request = $creator->fromGlobals();

        $converter = new GenerCodeConverter();

        $irequest = $converter->convertLaravelRequest($this->request);
        $container->instance("illu_request", $irequest);

        //$httpFoundationFactory = new GenerCodeSymfonyBridge();
        //$http_request = $httpFoundationFactory->createRequest($this->request);
        //$container->instance("request", $http_request);

        /*$container->instance(Request::class, $this->request);
        $container->singleton(\Symfony\Component\HttpFoundation\Request::class, function($app) {
            return $app->get(Request::class);
        });

        
        
        $container->singleton(\Illuminate\Http\Request::class, function($app) {
            return $app->get("request");
        });
        
        */
        $container->instance(\Illuminate\Contracts\Container\Container::class, $container);
    }

    static function setEnvDir($dir) {
        GenerCodeKernel::setEnvDir($dir);
    }

    static function setConfigDir($dir) {
        GenerCodeKernel::setConfigDir($dir);
    }


    public function __call($method, $args) {
        return call_user_func_array([$this->app, $method], $args);
    }


    public function initMiddleware()
    {
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();


        $container = $this->app->getContainer();

        $this->app->add(new Middleware\UserMiddleware(
            $container
        ));

        $this->app->add(new Middleware\LaravelPsr15Wrapper($container, $container->make(\Illuminate\Session\Middleware\StartSession::class)));
        $this->app->add(new Middleware\LaravelPsr15Wrapper($container, $container->make(\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class)));
        $this->app->add(new Middleware\JsonContent());
      //  $this->app->add($container->make(\App\Http\Middleware\VerifyCsrfToken::class));
     
        $errorMiddleware = $this->app->addErrorMiddleware(true, true, true);

        $app = $this->app;
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

        $this->app->add(new GenerCodeSlimCors(
            $container
        ));
    }


    public function addRoutes()
    {
        $this->app->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });

        $this->app->map(['POST', 'PUT', 'DELETE'], '/data/{model}', function (Request $request, Response $response, $args) {
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
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->app->put('/data/{model}/resort', function (Request $request, Response $response, $args) {
            $modelController = $this->get(Controllers\ModelController::class);
            $results = $modelController->resort($args["model"], new Fluent($request->getParsedBody()));
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });



        $this->app->get('/data/{model}[/{state}]', function (Request $request, Response $response, $args) {
            $state = (isset($args["state"])) ? $args["state"] : "get";
            $repoController = $this->get(Controllers\RepositoryController::class);
            if ($state == "active") {
                $results = $repoController->getActive($args["model"], new Fluent($request->getQueryParams()));
                if (!$results) $results = "{}";
            } else {
                $results = $repoController->get($args["model"], new Fluent($request->getQueryParams()));
            }
            $response->getBody()->write(json_encode($results, JSON_INVALID_UTF8_SUBSTITUTE));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });

        $this->app->get('/count/{model}', function (Request $request, Response $response, $args) {
            $name = $args['model'];
            $repoController = $this->get(Controllers\RepositoryController::class);
            $results = $repoController->count($args["model"], new Fluent($request->getQueryParams()));
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->app->get("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(Controllers\AssetController::class);
            $data = $assetController->getAsset($args["model"], $args["field"], $args["id"]);
            echo $data;
            exit;
        });

        $this->app->get("/asset/exists/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(Controllers\AssetController::class);
            if (!$assetController->exists($args["model"], $args["field"], $args["id"])) {
                throw new \Exception("Asset doesn't exist");
            }
            return $response;
        });


        $this->app->post("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(Controllers\AssetController::class);
            $data = $assetController->patchAsset($args["model"], $args["field"], $args["id"]);
            $response->getBody()->write(json_encode($data));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->app->delete("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(Controllers\AssetController::class);
            $data = $assetController->removeAsset($args["model"], $args["field"], $args["id"]);
            $response->getBody()->write(json_encode($data));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->app->get("/reference/{model}/{field}[/{id}]", function ($request, $response, $args) {
            $refController = $this->get(Controllers\ReferenceController::class);
            $params = new Fluent($request->getQueryParams());
            $id = (isset($args["id"])) ? $args["id"] : 0;
            $results = $refController->load($args["model"], $args["field"], $id, $params);
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });





        $this->app->map(["POST", "PUT"], "/import/{name}", function ($request, $response, $args) {
            return $response;
        });

        $this->app->map(["POST", "DELETE"], "/bulk/{name}", function ($request, $response, $args) {
            return $response;
        });

        $this->app->group("/audit", function(RouteCollectorProxy $group) {
            $group->get("/history/{name}/{id}", function($request, $response, $args) {
                $params = $request->getQueryParams();
                $audit = $this->get(Controllers\AuditController::class);
                $obj = $audit->getObjectAt($args["name"], $args["id"], $params["last-published"]);
                $response->getBody()->write(json_encode($obj));
                return $response
                ->withHeader("Content-Type", "application/json");
            });
        });


        $this->app->group("/user", function (RouteCollectorProxy $group) {
            $group->get("/dictionary", function ($request, $response, $args) {
                $profile = $this->get(\GenerCodeOrm\Profile::class);
                $dict = file_get_contents($this->config->get("dictionary") . $profile->name . ".json");
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
                $profileController = $this->get(Controllers\ProfileController::class);
                $response = $profileController->logout($converter->convertLaravelRequest($request), $converter->convertLaravelResponse($response), $args["name"]);
                return $converter->convertPsrResponse($response);
            });
        });


        $this->app->group("/dispatch", function (RouteCollectorProxy $group) {
            $group->get("/status/{id}", function (Request $request, Response $response, $args) {
                $repoController = $this->get(Controllers\RepositoryController::class);
                $data = $repoController->getActive("queue", new Fluent(["__fields"=>["progress"], "--id"=>$args["id"]]));
                $response->getBody()->write(json_encode($data));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });


            $group->delete("/remove/{id}", function (Request $request, Response $response, $args) {
                $modelController = $this->get(Controllers\ModelController::class);
                $results = $modelController->delete("queue", new Fluent(["--id"=>$args["id"]]));
                $response->getBody()->write(json_encode($results));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });
        });
    }


    function run() {
        $this->app->run($this->request);
    }
}