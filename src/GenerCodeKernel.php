<?php
namespace GenerCodeSlim;

use Illuminate\Container\Container;



class GenerCodeKernel {

    protected $providers = [
        \Illuminate\Database\DatabaseServiceProvider::class,
        \Illuminate\Filesystem\FilesystemServiceProvider::class,
        \Illuminate\Auth\AuthServiceProvider::class,
        \Illuminate\Hashing\HashServiceProvider::class,
        \Illuminate\Session\SessionServiceProvider::class,
        \Illuminate\Cookie\CookieServiceProvider::class,
        \Illuminate\Events\EventServiceProvider::class
        ];

    static $env_dir;
    static $config_dir;

    protected $active_providers = [];


    static function setEnvDir($dir) {
        self::$env_dir = $dir;
    }

    static function setConfigDir($dir) {
        self::$config_dir = $dir;
    }

    function buildContainer() {
        $configs = new Configs(self::$env_dir);
        $config_dir = (self::$config_dir) ? self::$config_dir : self::$env_dir . "/configs.php";
        $configs->load($config_dir);

        $container = new Container();
        $container->instance("config", $configs);

        $container->instance(Container::class, $container);

        $active = [];

        foreach($this->providers as $prov) {
            $p = new $prov($container);
            $p->register();
            $active[$prov] = $p;
        }
      

        $container->singleton(\Illuminate\Database\DatabaseManager::class, function ($app) {
            return $app->make('db');
        });


        $container->singleton(\Illuminate\Database\Connection::class, function($app) {
            return $app->make('db')->connection();
        });

        foreach($active as $a) {
            if (method_exists($a, "boot")) {
                $a->boot();
            }
        }

        return $container;
    }

    static function buildApp(\GenerCodeOrm\GenerCodeContainer $container) {
        $app = new Application();
        $app->add(new \GenerCodeCmd\DictionaryCommand($container));
        $app->add(new \GenerCodeCmd\DownloadCommand($container));
        $app->add(new \GenerCodeCmd\MigrationCommand($container));
        $app->add(new \GenerCodeCmd\PublishCommand($container));
        $app->add(new \GenerCodeCmd\UploadCommand($container));
        $app->add(new \GenerCodeCmd\CdnCommand($container));
        return $app;
    }
}