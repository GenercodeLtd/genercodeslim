<?php
namespace GenerCodeSlim;

use \Illuminate\Support\Fluent;


class ConfigsSetup {

    function loadEnvDetails($env_dir) {
        $dotenv = \Dotenv\Dotenv::createImmutable($env_dir);
        $dotenv->load();
        return $_ENV;
    }

    function loadConfigs(array $environment, $config_file = __DIR__ . "/Configs.php") : Fluent{
        $env = new Fluent($environment); //set so it can be used inside configs file
        $configs = require($config_file);
        return new Fluent($configs);
    }

}