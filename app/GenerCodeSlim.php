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

    function loadConfigs($env_dir, $config_file = __DIR__ . "/Configs.php") {
        $dotenv = \Dotenv\Dotenv::createImmutable($env_dir);
        $dotenv->load();

        $env = new Fluent($_ENV);

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


    function addDependencies() {

        $container = $this->app->getContainer();
        $factory = new ConnectionFactory($container);
        $manager = new DatabaseManager($container, $factory);
        $container->instance($manager::class, $manager); 

        $profileFactory = new \PressToJam\ProfileFactory();
        $container->instance("factory", $profileFactory);

        $container->bind(UserMiddleware::class, function($app) {
            $token = $app->make(TokenHandler::class);
            $token->setConfigs($app->config->token);
            return new UserMiddleware($app, $app->get("factory"), $token);
        });

        $container->bind(\GenerCodeOrm\ProfileController::class, function($app) {
            return new \GenerCodeOrm\ProfileController($app->get(\Illuminate\Database\DatabaseManager::class), $app->get(\GenerCodeOrm\Profile::class));
        });

        $container->bind(\GenerCodeOrm\ModelController::class, function($app) {
            return new \GenerCodeOrm\ModelController($app->get(\Illuminate\Database\DatabaseManager::class), $app->get(\GenerCodeOrm\Profile::class), $app->make(\GenerCodeOrm\Hooks::class));
        });

       // $container->bind(\Illuminate\FileSystem\FileSystemManager::class, function($app) {

        //})

      
    }





    function initMiddleware() {
        
        $this->app->addRoutingMiddleware();

        $this->app->add(function($request, $handler) {
            //write our error messages and reset to work with error handling
            try {
                $response = $handler->handle($request);
            } catch(Exceptions\ValidationException $e) {
                throw $e;
            } catch(\Exception $e) {
                $code = $e->getCode();
                if ($code > 500) $code = 500;
                $excep = new HttpException($request, $e->getMessage(), $code, $e);
                if (method_exists($e, "getTitle")) {
                    $excep->setTitle($e->getTitle());
                }
                if (method_exists($e, "getDescription")) {
                    $excep->setDescription($e->getDescription());
                }
                throw $excep;
            } 
            return $response;
        });


        $this->app->addBodyParsingMiddleware();


        $this->app->add($this->app->getContainer()->get(UserMiddleware::class));

       // $errorMiddleware = $this->app->addErrorMiddleware(true, true, true);
       // $errorHandler = $errorMiddleware->getDefaultErrorHandler();
       // $errorHandler->forceContentType('application/json');

        
        $this->app->add(function($request, $handler) {
            $response = $handler->handle($request);
            return $response->withHeader('Access-Control-Allow-Origin', $this->config->cors['origin'])
            ->withHeader('Access-Control-Allow-Headers', implode(",", $this->config->cors['headers']))
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Content-Type', 'application/json');
        });
        
    }


    function addRoutes() {

        $this->app->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });

        $this->app->map(['POST', 'PUT', 'DELETE'], '/data/{model}', function (Request $request, Response $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->$method($args["model"], $request->getParsedBody());
            $response->getBody()->write(json_encode($results));
            return $response;
        });


        $this->app->put('/data/{model}/resort', function (Request $request, Response $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->resort($args["model"], $request->getParsedBody());
            $response->getBody()->write(json_encode($results));
            return $response;
        });
    
        

        $this->app->get('/data/{model}[/{state}]', function (Request $request, Response $response, $args) {
            $state = (isset($args["state"])) ? $args["state"] : "get";
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->get($args["model"], $request->getQueryParams(), $state);
            $response->getBody()->write(json_encode($results));
            return $response;
        });

        $this->app->get('/count/{model}', function (Request $request, Response $response, $args) {
            $name = $args['model']; 
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->count($args["model"], $request->getQueryParams());
            $response->getBody()->write(json_encode($results));
            return $response;
        });


        $this->app->get("/asset/{model}/{field}/{id}", function($request, $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $fmanager = new \Illuminate\Filesystem\FilesystemManager($this);
            $data = $modelController->getAsset($fmanager, $this->config["filesystems.disks.s3"]['prefix_path'], $args["model"], $args["field"], $args["id"]);
            echo $data;
            exit;
        });

        $this->app->get("/reference/{model}/{field}", function($request, $response, $args) {
            $name = $args["model"];
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->reference($args["model"], $args["field"], $request->getQueryParams()); 
            $response->getBody()->write(json_encode($results));
            return $response;
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
                return $response;        
            });


            $group->get("/site-map", function($request, $response) {
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->getSitemap()));
                return $response;
            });

            $group->post('/login/{name}', function (Request $request, Response $response, $args) {
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $id = $profileController->login($args["name"], $request->getParsedBody());
                $tokenHandler = $this->get(TokenHandler::class);
                $tokenHandler->setConfigs($this->config->token);
                $response = $tokenHandler->save($response, $args["name"], $id);
                $response->getBody()->write(json_encode("success"));
                return $response;
            });
    
    
            $group->post('/anon/{name}', function (Request $request, Response $response, $args) {
                $name = $args['name'];
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $profile = $profileController->ceateAnon($args["name"], $request->getParsedBody());
                $tokenHandler = $this->get(TokenHandler::class);
                $response = $tokenHandler->save($response, $profile);
                $response->getBody()->write(json_encode("success"));
                return $response;
            });

            
            $group->put("/switch-tokens", function (Request $request, Response $response, $args) {
                $tokenHandler = $this->get(TokenHandler::class);
                $tokenHandler->setConfigs($this->config->token);
                $response = $tokenHandler->switchTokens($request, $response);
                $response->getBody()->write(json_encode("success"));
                return $response;
            });
            
            $group->get("/check-user", function (Request $request, Response $response, $args)  {
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->checkUser()));
                return $response;
            });

            $group->post("/change-role[/{role}]", function (Request $request, Response $response, $args) {
                $role = (isset($args['role'])) ? $args["role"] : "";
                if (!$role) {
                    $this->user->role = "";
                    $response = $this->user->save($response);
                } else if ($role != $user->role) {
                    $this->user->role = ""; //reset so we get the correct initial perms
                    $perms = Factory::createPerms($this->user);
                    if ($role) {
                        $roles = $perms->getRoles();
                        if (in_array($role, $roles)) {
                            $user->role = $role;
                        }
                    }
                    $response = $this->user->save($response);
                }
                $response->getBody()->write(json_encode($this->user));
                return $response;
            });

            
            $group->post("/logout", function (Request $request, Response $response, $args) {
                $tokenHandler = $this->get(TokenHandler::class);
                $response = $tokenHandler->logout($response);
                $response->getBody()->write(json_encode($this->user));
                return $response;
            });
        });

    }

    public function run() {
        $this->app->run();
    }


}