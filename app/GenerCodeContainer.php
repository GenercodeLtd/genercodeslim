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


class GenerCodeContainer extends Container {

    function loadEnvDetails($env_dir) {
        $dotenv = \Dotenv\Dotenv::createImmutable($env_dir);
        $dotenv->load();

        return $_ENV;
    }

    function loadConfigs(array $environment, $config_file = __DIR__ . "/Configs.php") {
        $env = new Fluent($environment);
        return require($config_file);
    }

  
    function addDependencies($configs = []) {

        $fluent = new Fluent($configs);
        $this->instance('config', $fluent);

        $factory = new ConnectionFactory($this);
        $manager = new DatabaseManager($this, $factory);
        $this->instance(DatabaseManager::class, $manager); 

        $profileFactory = new \PressToJam\ProfileFactory();
        $this->instance("factory", $profileFactory);

        $this->bind(TokenHandler::class, function($app) {
            $token = new TokenHandler();
            $token->setConfigs($app->config->token);
            return $token;
        });


        $this->bind(\GenerCodeOrm\Hooks::class, function($app) {
            $hooks = new \GenerCodeOrm\Hooks($app);
            if ($app->config->hooks) $hooks->loadHooks($app->config->hooks);
            return $hooks;
        });


        $this->bind(\GenerCodeOrm\Queue::class, function($app) {
            return new \GenerCodeOrm\Queue($app);
        });

        $this->bind(\GenerCodeOrm\SchemaRepository::class, function($app) {
            $profile = $app->get(\GenerCodeOrm\Profile::class);
            return new \GenerCodeOrm\SchemaRepository($profile->factory);
        });

        $this->bind(\GenerCodeOrm\Model::class, function($app) {
            $dbmanager = $app->get(\Illuminate\Database\DatabaseManager::class);
            $schema = $app->make(\GenerCodeOrm\SchemaRepository::class);
            return new \GenerCodeOrm\Model($dbmanager->connection(), $schema);
        });


        $this->bind(\GenerCodeOrm\Reference::class, function($app) {
            $dbmanager = $app->get(\Illuminate\Database\DatabaseManager::class);
            $schema = $app->make(\GenerCodeOrm\SchemaRepository::class);
            return new \GenerCodeOrm\Reference($dbmanager->connection(), $schema);
        });

        $this->bind(\GenerCodeOrm\Repository::class, function($app) {
            $dbmanager = $app->get(\Illuminate\Database\DatabaseManager::class);
            $schema = $app->make(\GenerCodeOrm\SchemaRepository::class);
            return new \GenerCodeOrm\Repository($dbmanager->connection(), $schema);
        });
        

        $this->bind(\Illuminate\Filesystem\FilesystemManager::class, function($app) {
            return new \Illuminate\Filesystem\FilesystemManager($app);
        });

        $this->bind(\GenerCodeOrm\ProfileController::class, function($app) {
            return new \GenerCodeOrm\ProfileController($app);
        });

        $this->bind(\GenerCodeOrm\ModelController::class, function($app) {
            return new \GenerCodeOrm\ModelController($app);
        });

        $this->bind(\GenerCodeOrm\FileHandler::class, function($app) {
            $file = $app->make(\Illuminate\Filesystem\FilesystemManager::class);
            $prefix = $app->config["filesystems.disks.s3"]['prefix_path'];
            $fileHandler = new \GenerCodeOrm\FileHandler($file, $prefix);
            return $fileHandler;
        });
 
    }

}