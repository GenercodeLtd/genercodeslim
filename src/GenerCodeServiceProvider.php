<?php
namespace GenerCodeSlim;

use Illuminate\Support\ServiceProvider;

class GenerCodeServiceProvider extends ServiceProvider {

    public function register()
    {
        
    }

    public function boot() {
        $app = app();
    
        $app->instance("entity_factory", new \PressToJam\EntityFactory());
    
        if ($app->runningInConsole()) {
            $profile = $app->make(\PressToJam\Profile\AdminProfile::class);
            $profile->id = 1;
            $app->instance("profile", $profile);
        }
    }
}