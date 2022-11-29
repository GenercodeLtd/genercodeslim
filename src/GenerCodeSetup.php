<?php
namespace GenerCodeSlim;

use Symfony\Component\Console\Application;
use Illuminate\Container\Container;

class GenerCodeSetup {

    static function build(string $env_dir, string $config_dir = null) {
        $configs = new Configs($env_dir);
        $config_dir ??= $env_dir . "/configs.php";
        $configs->load($config_dir);

        $container = new Container();
        $container->instance("config", $configs);

        
        $gcprovider = new GenerCodeOrm\GenerCodeServiceProvider($container);
        $gcprovider->register();
        $container->instance("gcprovider", $gcprovider);

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