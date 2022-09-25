<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Container\Container as Container;
use \Slim\Psr7\Environment as Environment;
//require(__DIR__ . "/../app/GenerCodeSlim.php");
require(__DIR__ . "/../vendor/autoload.php");

\GenerCodeOrm\regAutoload("PressToJam", __DIR__ . "/../../ptjmanager/repos/ptj");

final class SlimTest extends TestCase
{
    protected $configs;
    protected $app;

    public function setUp(): void
    {
       /* Config::set("db.host", "localhost");
        Config::set("db.driver", "mysql");
        Config::set("db.database", "database");
        Config::set("db.username", "root");
        Config::set("db.password", "password");
        Config::set("db.charset", "utf8");
        Config::set("db.collation", "utf8_unicode_ci");*/
       /* $container = new Container();
        $capsule = new Capsule($container);
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'database',
            'username'  => 'root',
            'password'  => 'password',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]);
        $capsule->setAsGlobal();*/
        $configs = [
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'database',
            'username'  => 'root',
            'password'  => 'password',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ];

        $_SESSION = array();

        $config = [
            'settings' => [
                'displayErrorDetails' => true,
            ],
        ];

    
        $this->app = new GenerCodeSlim\GenerCodeSlim();
        $this->app->init($configs);

        /*
        $container = $app->getContainer();
$container['logger'] = function ($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler("../logs/app.log");
    $logger->pushHandler($file_handler);
    return $logger;
};*/
    }

    public function testHome() {
   
        $factory = new Slim\Psr7\Factory\RequestFactory();
       $request = $factory->createRequest('GET', '/data/projects');
      //  $request = \Slim\Psr7\Request::createFromEnvironment($env);
      $app = $this->app->getApp();
        $response = $app->handle($request);

        var_dump($response->getBody());

        $this->assertContains('home', $response->getBody());
    }


    public function testCookie() {
   
        $factory = new Slim\Psr7\Factory\RequestFactory();
       $request = $factory->createRequest('GET', '/data/projects');
      //  $request = \Slim\Psr7\Request::createFromEnvironment($env);
      $app = $this->app->getApp();
        $response = $app->handle($request);

        var_dump($response->getBody());

        $this->assertContains('home', $response->getBody());
    }


}