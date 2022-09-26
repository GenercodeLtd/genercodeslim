<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Container\Container as Container;
use \Slim\Psr7\Environment as Environment;
use \Illuminate\Support\Fluent;

//require(__DIR__ . "/../app/GenerCodeSlim.php");
require(__DIR__ . "/../vendor/autoload.php");

\GenerCodeOrm\regAutoload("PressToJam", __DIR__ . "/../../genercodeltd/repos/ptj");

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
        $configs = $this->app->loadConfigs( __DIR__ . "/../../localapi",  __DIR__ . "/../../localapi/configs.php");
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

    public function testUpload() {

        $_FILES = ["asseter"=> [
            "size"=>500,
            "tmp_name"=>__DIR__ . "/testproject/defaultpdf.pdf",
            "error"=>0,
            "name"=>"defaultpdf.pdf"
        ]];

        
        $factory = new Slim\Psr7\Factory\RequestFactory();
        $request = $factory->createRequest('PUT', '/data/tester', [], ["api-auth"=>"eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzUxMiJ9.eyJpYXQiOjE2NjQxOTAwOTgsImlzcyI6InNsaW0ubG9jYWxob3N0IiwibmJmIjoxNjY0MTkwMDk4LCJleHAiOjE2NjkzNzQwOTgsInUiOiJhY2NvdW50cyIsImkiOjF9.fL9Nht78FBXE9lSL_p3uTsExPYWecT6hL0E08PjP65KjFL2V6sAD07Gokx_wT_RUftl4EdGZE5Yil6JIKSqz_Q"]);
        $request = $request->withParsedBody(["--id"=>2, "stringer"=>"strs", "number"=>5]);
        $app = $this->app->getApp();
        try {
            $response = $app->handle($request);
        } catch(\Throwable $e) {
            echo "Message is " . $e->getMessage();
        }

        var_dump($response->getBody()->getContents());
    }


}