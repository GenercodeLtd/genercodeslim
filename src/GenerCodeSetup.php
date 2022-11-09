<?php
namespace GenerCodeSlim;

use Symfony\Component\Console\Application;

class GenerCodeSetup {

    static function build(string $env_dir, string $config_dir = null) {
        $configs = new Configs($env_dir);
        $config_dir ??= $env_dir . "/configs.php";
        $configs->load($config_dir);

        $container = new \GenerCodeOrm\GenerCodeContainer();
        $container->bindConfigs($configs);

        return $container;
    }

    static function buildApp(\GenerCodeOrm\GenerCodeContainer $container) {
        $app = new Application();
        $app->add(new \GenerCodeCmd\DictionaryCommand($container));
        $app->add(new \GenerCodeCmd\DownloadCommand($container));
        $app->add(new \GenerCodeCmd\MigrationCommand($container));
        $app->add(new \GenerCodeCmd\PublishCommand($container));
        $app->add(new \GenerCodeCmd\UploadCommand($container));
        return $app;
    }
}