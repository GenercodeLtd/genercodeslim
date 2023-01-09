<?php
namespace GenerCodeSlim;

use Illuminate\Container\Container;
use Illuminate\Support\Env;

if (!function_exists('env')) {
    function env($key, $default) {
        return Env::get($key, $default);
    }
}

class ConfigRegistration {

    /*
    $env_dir = environment directory / path to .env file
    */


    static function loadEnvironment(string $env_dir) {
        $dotenv = \Dotenv\Dotenv::createImmutable($env_dir);
        $dotenv->load();
    }


    static function loadConfigs(Container $app, string $config_file = __DIR__ . "/Configs.php") {
        $env = function($key, $default = null) {
            return Env::get($key, $default);
        };

        if (!file_exists($config_file)) {
            throw new \Exception("Config file doesn't exist at: " . $config_file);
        }

        $configs = require($config_file);
        foreach($configs as $key=>$val) {
            $app['config']->set($key, $val);
        }
    }

}