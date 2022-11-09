<?php
namespace GenerCodeSlim;

use \Illuminate\Support\Fluent;


class Configs extends \Illuminate\Config\Repository {

    /*
    $env_dir = environment directory / path to .env file
    */

    function __construct(string $env_dir) {
        $dotenv = \Dotenv\Dotenv::createImmutable($env_dir);
        $dotenv->load();
    }


    function load(string $config_file = __DIR__ . "/Configs.php") {
        $env = function($key, $default = null) {
            return (isset($_ENV[$key])) ? $_ENV[$key] : $default;
        };

        $configs = require($config_file);
        foreach($configs as $key=>$val) {
            $this->set($key, $val);
        }
    }

}