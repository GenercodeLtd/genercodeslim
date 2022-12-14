<?php
namespace GenerCodeSlim;

use Illuminate\Container\Container;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;



class GenerCodeApplication extends Container {

    protected $basePath;
    protected $appPath;

    protected $serviceProviders = [
        \Illuminate\Database\DatabaseServiceProvider::class,
        \Illuminate\Filesystem\FilesystemServiceProvider::class,
        \Illuminate\Auth\AuthServiceProvider::class,
        \Illuminate\Hashing\HashServiceProvider::class,
        \Illuminate\Session\SessionServiceProvider::class,
        \Illuminate\Cookie\CookieServiceProvider::class,
        \Illuminate\Events\EventServiceProvider::class,
        \Illuminate\Queue\QueueServiceProvider::class
        ];

    protected $loadedProviders;
  
    protected $booted = false;


    public function __construct($basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerServiceProviders();
    }



    static function setEnvDir($dir) {
        self::$env_dir = $dir;
    }

    static function setConfigDir($dir) {
        self::$config_dir = $dir;
    }


    protected function registerBaseBindings()
    {
        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance(Container::class, $this);
        $this->instance(\Illuminate\Contracts\Container\Container::class, $this);
        //$this->singleton(Mix::class);

        /*$this->singleton(PackageManifest::class, function () {
            return new PackageManifest(
                new Filesystem, $this->basePath(), $this->getCachedPackagesPath()
            );
        });*/


        $this->singleton(GenerCodeConverter::class, function($app) {
            $cls = GenerCodeConverter::class;
            return new $cls();
        });

        $this->singleton(\Illuminate\Database\DatabaseManager::class, function ($app) {
            return $app->make('db');
        });


        $this->singleton(\Illuminate\Database\Connection::class, function($app) {
            return $app->make('db')->connection();
        });


        $this->bind(\Illuminate\Contracts\Cookie\QueueingFactory::class, function($app) {
            return $app->get("cookie");
        });

        $this->bind(\Illuminate\Contracts\Session\Session::class, function($app) {
            return $app->get("session");
        });


        $this->bind(\Illuminate\Session\SessionManager::class, function($app) {
            return $app->get("session");
        });

        $this->singleton("config", function($app) {
            return new \Illuminate\Config\Repository();
        });
    }


    protected function registerBaseServiceProviders()
    {
        $this->register(new EventServiceProvider($this));
        //$this->register(new LogServiceProvider($this));
        //$this->register(new RoutingServiceProvider($this));
    }


    public function resolveProvider($provider)
    {
        return new $provider($this);
    }



    public function register($provider, $force = false)
    {
        if (($registered = $this->getProvider($provider)) && ! $force) {
            return $registered;
        }

        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        // If there are bindings / singletons set as properties on the provider we
        // will spin through them and register them with the application, which
        // serves as a convenience layer while registering a lot of bindings.
        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $key = is_int($key) ? $value : $key;

                $this->singleton($key, $value);
            }
        }

        $this->loadedProviders[] = $provider;

        // If the application has already booted, we will call this boot method on
        // the provider class so it has an opportunity to do its boot logic and
        // will be ready for any usage by this developer's application logic.
        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        return $provider;
    }


    function registerServiceProviders() {
    
        $active = [];

        foreach($this->serviceProviders as $provider) {
            $this->register($provider);
        }
    }


    public function getProvider($provider)
    {
        return array_values($this->getProviders($provider))[0] ?? null;
    }

    public function getProviders($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return Arr::where($this->serviceProviders, function ($value) use ($name) {
            return $value instanceof $name;
        });
    }


    protected function bootProvider(ServiceProvider $provider)
    {
        $provider->callBootingCallbacks();

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        $provider->callBootedCallbacks();
    }



    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * Boot the application's service providers.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->isBooted()) {
            return;
        }

        // Once the application has booted we will also fire some "booted" callbacks
        // for any listeners that need to do work after this initial booting gets
        // finished. This is useful when ordering the boot-up processes we run.
        //$this->fireAppCallbacks($this->bootingCallbacks);

        array_walk($this->loadedProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->booted = true;

        //$this->fireAppCallbacks($this->bootedCallbacks);
    }


    public function basePath($path = '')
    {
        return $this->basePath.($path != '' ? DIRECTORY_SEPARATOR.$path : '');
    }


    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->bindPathsInContainer();

        return $this;
    }


    /**
     * Get the path to the application "app" directory.
     *
     * @param  string  $path
     * @return string
     */
    public function path($path = '')
    {
        $appPath = $this->appPath ?: $this->basePath.DIRECTORY_SEPARATOR.'api';

        return $appPath.($path != '' ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Bind all of the application paths in the container.
     *
     * @return void
     */
    protected function bindPathsInContainer()
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        /*$this->instance('path.config', $this->configPath());
        $this->instance('path.public', $this->publicPath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.resources', $this->resourcePath());
        $this->instance('path.bootstrap', $this->bootstrapPath());

        $this->useLangPath(value(function () {
            if (is_dir($directory = $this->resourcePath('lang'))) {
                return $directory;
            }

            return $this->basePath('lang');
        }));*/
    }
}