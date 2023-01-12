<?php
namespace GenerCodeSlim;

use Illuminate\Support\ServiceProvider;

class GenerCodeServiceProvider extends ServiceProvider {

    public function register()
    {
        
    }

    public function boot() {
        $domain = $_ENV['x-domain'];
        config(["database.default" => $domain]);
        config(["filesystems.default"=>"s3" . $domain]);
        config(["mail.default"=>$domain]);
        config(["document.default"=> $domain]);
        config(["auth.providers.users.connection"=> $domain]);
        config(["auth.passwords.users.connection"=> $domain]);
        
        $queue = config("queue.connections.sqs." . $domain . "_queue");
        config(["queue.connections.sqs.queue"=> $queue]);
        $this->app->instance("entity_factory", new \PressToJam\EntityFactory());
    }
}