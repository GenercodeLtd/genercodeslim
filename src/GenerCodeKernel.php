<?php
namespace GenerCodeSlim;

use \Illuminate\Container\Container;
use Illuminate\Support\Env;

if (!function_exists('env')) {
    function env($key, $default) {
        return Env::get($key, $default);
    }
}

class GenerCodeKernel
{

    protected $env_dir;
    protected $config_file;


    public function __construct($env_dir, $config_file = null) {
        $this->env_dir = $env_dir;
        $this->config_file = $config_file ?? $env_dir . "/configs.php";

        if (!file_exists($this->config_file)) {
            throw new \Exception("Config file doesn't exist at: " . $this->config_file);
        }
    }


    protected function loadEnvironment() {
        $dotenv = \Dotenv\Dotenv::createImmutable($this->env_dir);
        $dotenv->load();
    }


    protected function loadConfigs(Container $app) {
        $env = function($key, $default = null) {
            return Env::get($key, $default);
        };
        

        $configs = require($config_file);
        foreach($configs as $key=>$val) {
            $app['config']->set($key, $val);
        }
    }


    public function load() {
        $container = new GenerCodeApplication();

        $this->loadEnvironment();
        $this->loadConfigs($container);
        
        $container->boot();
        return $container;
    }

}