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


class GenerCodeSlim
{
    public function create(Container $container)
    {
        AppFactory::setContainer($container);
        return AppFactory::create();
    }


    public function initMiddleware($app)
    {
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();


        $container = $app->getContainer();

        $app->add(new UserMiddleware(
            $container,
            $container->get("factory"),
            $container->make(TokenHandler::class)
        ));


        $app->add(new \Tuupola\Middleware\CorsMiddleware([
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


        $errorMiddleware = $app->addErrorMiddleware(true, true, true);

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

            $payload = ['error' => $exception->getMessage(), "file"=>$exception->getFile(), "line"=>$exception->getLine()];

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
            ->withHeader('Access-Control-Allow-Origin', $container->config->cors['origin'])
            ->withHeader('Access-Control-Allow-Headers', implode(",", $container->config->cors['headers']))
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Content-Type', 'application/json');
        };
        $errorMiddleware->setDefaultErrorHandler($errFunc);
    }


    public function addRoutes($app)
    {
        $app->options('/{routes:.+}', function ($request, $response, $args) {
            return $response;
        });

        $app->map(['POST', 'PUT', 'DELETE'], '/data/{model}', function (Request $request, Response $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
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


        $app->put('/data/{model}/resort', function (Request $request, Response $response, $args) {
            $modelController = $this->get(\GenerCodeOrm\ModelController::class);
            $results = $modelController->resort($args["model"], new Fluent($request->getParsedBody()));
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });



        $app->get('/data/{model}[/{state}]', function (Request $request, Response $response, $args) {
            $state = (isset($args["state"])) ? $args["state"] : "get";
            $repoController = $this->get(\GenerCodeOrm\RepositoryController::class);
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

        $app->get('/count/{model}', function (Request $request, Response $response, $args) {
            $name = $args['model'];
            $repoController = $this->get(\GenerCodeOrm\RepositoryController::class);
            $results = $repoController->count($args["model"], new Fluent($request->getQueryParams()));
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $app->get("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(\GenerCodeOrm\AssetController::class);
            $data = $assetController->getAsset($args["model"], $args["field"], $args["id"]);
            echo $data;
            exit;
        });


        $app->patch("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            $assetController = $this->get(\GenerCodeOrm\AssetController::class);
            $data = $assetController->patchAsset($args["model"], $args["field"], $args["id"], $request->getBody());
            $response->getBody()->write(json_encode($data));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $app->delete("/asset/{model}/{field}/{id}", function ($request, $response, $args) {
            echo "Going to asset controller";
            $assetController = $this->get(\GenerCodeOrm\AssetController::class);
            $data = $assetController->removeAsset($args["model"], $args["field"], $args["id"]);
            $response->getBody()->write(json_encode($data));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });


        $app->get("/reference/{model}/{field}[/{id}]", function ($request, $response, $args) {
            $repoController = $this->get(\GenerCodeOrm\RepositoryController::class);
            $params = $request->getQueryParams();
            $fluent = null;
            if ($params) {
                $fluent = new Fluent($params);
            }
            $id = (isset($args["id"])) ? $args["id"] : 0;
            $results = $repoController->reference($args["model"], $args["field"], $id, $fluent);
            $response->getBody()->write(json_encode($results));
            return $response
            ->withHeader('Content-Type', 'application/json');
        });





        $app->map(["POST", "PUT"], "/import/{name}", function ($request, $response, $args) {
            return $response;
        });

        $app->map(["POST", "DELETE"], "/bulk/{name}", function ($request, $response, $args) {
            return $response;
        });



        $app->group("/user", function (RouteCollectorProxy $group) {
            $group->get("/dictionary", function ($request, $response, $args) {
                $profile = $this->get(\GenerCodeOrm\Profile::class);
                $dict = file_get_contents($this->config->repo_root . "/Dictionary/" . $profile->name . ".json");
                $response->getBody()->write($dict);
                return $response
                ->withHeader('Content-Type', 'application/json');
            });


            $group->get("/site-map", function ($request, $response) {
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->getSitemap()));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            $group->post('/login/{name}', function (Request $request, Response $response, $args) {
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $id = $profileController->login($args["name"], new Fluent($request->getParsedBody()));
                $tokenHandler = $this->make(TokenHandler::class);
                $response = $tokenHandler->save($response, $args["name"], $id);
                $response->getBody()->write(json_encode(["--id"=>1]));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });

            $group->post('/login/token/{name}', function (Request $request, Response $response, $args) {
                $params = new Fluent($request->getParsedBody());
                $tokenHandler = $this->make(TokenHandler::class);
                return $tokenHandler->loginFromToken($params["token"], $response, $args["name"])
                ->withHeader('Content-Type', 'application/json');
            });


            $group->post('/anon/{name}', function (Request $request, Response $response, $args) {
                $name = $args['name'];
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
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
                $profileController = $this->get(\GenerCodeOrm\ProfileController::class);
                $response->getBody()->write(json_encode($profileController->checkUser()));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });


            $group->post("/logout", function (Request $request, Response $response, $args) {
                $tokenHandler = $this->get(TokenHandler::class);
                $response = $tokenHandler->logout($response);
                $response->getBody()->write(json_encode("success"));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });
        });


        $app->group("/dispatch", function (RouteCollectorProxy $group) {
            $group->get("/status/{id}", function (Request $request, Response $response, $args) {
                $model = $this->get(\GenerCodeOrm\Repository::class);
                $model->name = "queue";
                $model->fields = ["progress"];
                $model->where = ["--id"=>$args['id']];
                $data = $model->get();
                $response->getBody()->write(json_encode($data));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });


            $group->delete("/remove/{id}", function (Request $request, Response $response, $args) {
                $model = $this->get(\GenerCodeOrm\Repository::class);
                $model->name = "queue";
                $model->where = ["--id"=>$args['id']];
                $response->getBody()->write(json_encode($model->delete()));
                return $response
                ->withHeader('Content-Type', 'application/json');
            });
        });
    }
}