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
use \Illuminate\Database\Connectors\ConnectionFactory;
use \Illuminate\Database\DatabaseManager;
use \Illuminate\FileSystem\FileSystemManager;
use Psr\Log\LoggerInterface;


class GenerCodeSlim {

    protected App $app;

    function __construct() {
        $container = new Container();
        AppFactory::setContainer($container);
        $this->app = AppFactory::create();
    }


    function __get($key) {
        return $this->app->$key;
    }

    function getApp() {
        return $this->app;
    }

    function loadEnvDetails($env_dir) {
        $dotenv = \Dotenv\Dotenv::createImmutable($env_dir);
        $dotenv->load();

        return $_ENV;
    }

    function loadConfigs(array $environment, $config_file = __DIR__ . "/Configs.php") {
        $env = new Fluent($environment);
        return require($config_file);
    }

    function init($configs = []) {
        $fluent = new Fluent($configs);
        
        $container = $this->app->getContainer();
        
        
        $fluent['database.fetch'] = \PDO::FETCH_OBJ;
        $fluent['database.default'] = 'default';
        $connections = $fluent['database.connections'];
        $connections["default"] = $configs["db"];
        $fluent['database.connections'] = $connections;

        $container->instance('config', $fluent);
     
        $this->addDependencies();
        $this->initMiddleware();
        $this->addRoutes();
    }


    function setConfig($key, $value) {
        $container = $this->app->getContainer();
        $container["config"][$key] = $value;
    }


    function addDependencies($hooks_data = []) {

        $container = $this->app->getContainer();
        $factory = new ConnectionFactory($container);
        $manager = new DatabaseManager($container, $factory);
        $container->instance(DatabaseManager::class, $manager); 

        $profileFactory = new \PressToJam\ProfileFactory();
        $container->instance("factory", $profileFactory);

        $container->bind(UserMiddleware::class, function($app) {
            $token = $app->make(TokenHandler::class);
            $token->setConfigs($app->config->token);
            return new UserMiddleware($app, $app->get("factory"), $token);
        });

        $container->bind(Hooks::class, function($app) use ($hooks_data) {
            return new Hooks($hooks_data);
        });

        $container->bind(UserMiddleware::class, function($app) {
            $token = $app->make(TokenHandler::class);
            $token->setConfigs($app->config->token);
            return new UserMiddleware($app, $app->get("factory"), $token);
        });

        $container->bind(\Illuminate\Filesystem\FilesystemManager::class, function($app) {
            return new \Illuminate\Filesystem\FilesystemManager($app);
        });

        $container->bind(\GenerCodeOrm\ProfileController::class, function($app) {
            return new \GenerCodeOrm\ProfileController($app);
        });

        $container->bind(\GenerCodeOrm\ModelController::class, function($app) {
            return new \GenerCodeOrm\ModelController($app);
        });

        $container->bind(\GenerCodeOrm\FileHandler::class, function($app) {
            $file = $app->make(\Illuminate\Filesystem\FilesystemManager::class);
            $prefix = $app->config["filesystems.disks.s3"]['prefix_path'];
            $fileHandler = new \GenerCodeOrm\FileHandler($file, $prefix);
            return $fileHandler;
        });

      
    }



    function errorHandler() {
        $app = $this->app;
        return function (
            Request $request,
            \Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails,
            ?LoggerInterface $logger = null
        ) use ($app) {
            $container = $app->getContainer();
            if ($logger) $logger->error($exception->getMessage());
        
            $payload = ['error' => $exception->getMessage()];
        
            $response = $app->getResponseFactory()->createResponse();
            $response->getBody()->write(
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            );
        
            return $response->withHeader('Access-Control-Allow-Origin', $container->config->cors['origin'])
            ->withHeader('Access-Control-Allow-Headers', implode(",", $container->config->cors['headers']))
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
            
        };
    }


    function initMiddleware() {
        
        $container = $this->app->getContainer();
        $this->app->addRoutingMiddleware();

        $this->app->addBodyParsingMiddleware();


        $this->app->add($this->app->getContainer()->get(UserMiddleware::class));


        $this->app->add(new \Tuupola\Middleware\CorsMiddleware([
            "origin"=>$container->config->cors["origin"],
            "methods" => ["GET", "POST", "PUT", "PATCH", "DELETE"],
            "headers.allow" => $container->config->cors['headers'],
            "headers.expose" => ["Etag"],
            "credentials" => true,
            "cache" => 86400,
            "error" => function ($request, $response, $arguments) {
                $data["status"] = "error";
                $data["message"] = $arguments["message"];
                return $response
                    ->withHeader("Content-Type", "application/json")
                    ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
            }
        ]));


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
            if ($logger) $logger->error($exception->getMessage());
        
            $payload = ['error' => $exception->getMessage()];
        
            $response = $app->getResponseFactory()->createResponse();
            $response->getBody()->write(
                json_encode($payload, JSON_UNESCAPED_UNICODE)
            );

            try {
                $status = $exception->getCode();
            } catch(\Exception $e) {
                $status = 500;
            }
        
            return $response->withStatus($status)
            ->withHeader('Access-Control-Allow-Origin', $container->config->cors['origin'])
            ->withHeader('Access-Control-Allow-Headers', implode(",", $container->config->cors['headers']))
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
            
        };
        $errorMiddleware->setDefaultErrorHandler($errFunc);
      
        
    }


    function addRoutes() {

        $this->app->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });

        $this->app->map(['POST', 'PUT', 'DELETE'], '/data/{model}', function (Request $request, Response $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $method = $request->getMethod();
            if ($method == "POST") $results = $modelController->create($args["model"], new Fluent($request->getParsedBody()));
            else if ($method == "PUT") $results = $modelController->update($args["model"], new Fluent($request->getParsedBody()));
            else if ($method == "DELETE") $results = $modelController->delete($args["model"], new Fluent($request->getParsedBody()));
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->app->put('/data/{model}/resort', function (Request $request, Response $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->resort($args["model"], $request->getParsedBody());
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });
    
        

        $this->app->get('/data/{model}[/{state}]', function (Request $request, Response $response, $args) {
            $state = (isset($args["state"])) ? $args["state"] : "get";
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->get($args["model"], new Fluent($request->getQueryParams()), $state);
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });

        $this->app->get('/count/{model}', function (Request $request, Response $response, $args) {
            $name = $args['model']; 
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->count($args["model"], new Fluent($request->getQueryParams()));
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $this->app->get("/asset/{model}/{field}/{id}", function($request, $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $data = $modelController->getAsset($args["model"], $args["field"], $args["id"]);
            $response->getBody()->write($data);
            return $response;
        });


        $this->app->delete("/asset/{model}/{field}/{id}", function($request, $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $data = $modelController->removeAsset($args["model"], $args["field"], $args["id"]);
            $response->getBody()->write(json_encode($data));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });

        
        $this->app->get("/reference/{model}/{field}", function($request, $response, $args) {
            $name = $args["model"];
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->reference($args["model"], $args["field"], new Fluent($request->getQueryParams())); 
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });



        

        $this->app->map(["POST", "PUT"], "/import/{name}", function($request, $response, $args) {
            return $response;
        });

        $this->app->map(["POST", "DELETE"], "/bulk/{name}", function($request, $response, $args) {
            return $response;
        });

        if (isset($_ENV['DEV'])) {
            $this->app->get("/debugsql/{name}/{state}", function($request, $response, $args) {
                return $response;
            });
        
            $this->app->get("/debuguser", function($request, $response, $args)  {
                return $response;
            });
        }

       

        $this->app->group("/user", function (RouteCollectorProxy $group) {

            $group->get("/dictionary", function($request, $response, $args)  {
                $profile = $this->get(\GenerCodeOrm\Profile::class);
                $dict = file_get_contents($this->config->repo_root . "/Dictionary/" . $profile->name . ".json");
                $response->getBody()->write($dict);
                return $response
                ->withHeader('Content-Type', 'application/json'); 
            });


            $group->get("/site-map", function($request, $response) {
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->getSitemap()));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            $group->post('/login/{name}', function (Request $request, Response $response, $args) {
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $id = $profileController->login($args["name"], new Fluent($request->getParsedBody()));
                $tokenHandler = $this->get(TokenHandler::class);
                $tokenHandler->setConfigs($this->config->token);
                $response = $tokenHandler->save($response, $args["name"], $id);
                $response->getBody()->write(json_encode("success"));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            $group->post('/login/token/{name}', function (Request $request, Response $response, $args) {
                $params = new Fluent($request->getParsedBody());
                $tokenHandler = $this->get(TokenHandler::class);
                $tokenHandler->setConfigs($this->config->token);
                return $tokenHandler->loginFromToken($params["token"], $response, $args["name"])
                ->withHeader('Content-Type', 'application/json');
            });
    
    
            $group->post('/anon/{name}', function (Request $request, Response $response, $args) {
                $name = $args['name'];
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $profile = $profileController->ceateAnon($args["name"], new Fluent($request->getParsedBody()));
                $tokenHandler = $this->get(TokenHandler::class);
                $response = $tokenHandler->save($response, $profile);
                $response->getBody()->write(json_encode("success"));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            
            $group->put("/switch-tokens", function (Request $request, Response $response, $args) {
                $tokenHandler = $this->get(TokenHandler::class);
                $tokenHandler->setConfigs($this->config->token);
                $response = $tokenHandler->switchTokens($request, $response);
                $response->getBody()->write(json_encode("success"));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });
            
            $group->get("/check-user", function (Request $request, Response $response, $args)  {
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->checkUser()));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            
            $group->post("/logout", function (Request $request, Response $response, $args) {
                $tokenHandler = $this->get(TokenHandler::class);
                $response = $tokenHandler->logout($response);
                $response->getBody()->write(json_encode($this->user));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });
        });

    }

    public function run() {
        $this->app->run();
    }


}