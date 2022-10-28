<?php

namespace GenerCodeSlim;

use \Illuminate\Container\Container;
use \Illuminate\Support\Fluent;

use Aws\Sqs\SqsClient; 
use Aws\Exception\AwsException;

use \GenerCodeOrm\Model;
use \GenerCodeOrm\Profile;

abstract class Job
{

    protected Container $app;
    protected \GenerCodeOrm\Profile $profile;
    protected $aws_config;
    protected $queue;
    protected $data;
    protected $configs = [];
    protected $progress;
    protected $message = "";
    protected $id = 0;
    protected $is_fifo = false;
   
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->profile = $app->make(\GenerCodeOrm\Profile::class);
        $this->queue = $this->app->config['queue']["sqsarn"];
        $this->aws_config = ["region" => $this->app->config['queue']["region"], "version"=>"latest"];
        $this->is_fifo = $this->app->config["queue"]["fifo"];
    }

    public function __set($key, $val)
    {
        if (property_exists($this, $key)) {
            $this->$key = $val;
        }
    }

    public function __get($key) {
        if (property_exists($this, $key)) return $this->$key;
    }

    function createClient() {
        return new SqsClient($this->aws_config);
    }

    function getModel() {
        return $this->app->makeWith(Model::class, ["name"=>"queue"]);
    }


    function addToQueue() {
        $name = get_class($this);
        $model = $this->getModel();
        
        $root = $model->root;
        $params  = [
            "user-login-id"=>$this->profile->id,
            "name"=>$name,
            "data"=>json_encode($this->data),
            "configs"=>json_encode($this->configs),
            "progress"=>"PENDING"
        ];

        $data = new \GenerCodeOrm\DataSet($model);
        foreach($model->root->cells as $alias=>$cell) {
            if(isset($params[$alias])) {
                $bind = new \GenerCodeOrm\Binds\SimpleBind($cell, $params[$alias]);
                $data->addBind($alias, $bind);
            }
        }

        $data->validate();

        $id = $model->setFromEntity()->insertGetId($data->toCellNameArr());

        $client = $this->createClient();

        $params = [
            'MessageBody' => json_encode(["id"=>$id, "name"=>$name, "profile"=>["name"=>$this->profile->name, "id"=>$this->profile->id]]),
            'QueueUrl' => $this->queue
        ];

        if (!$this->is_fifo) {
            $params["DelaySeconds"] = 10;
        } else {
            $params["MessageDeduplicationId"] = $name . "_" . $id;
            $params["MessageGroupId"] = $name;
        }


        $client->sendMessage($params);
       
        return $id;
    }


    function load() {
        $model = $this->getModel();
        $data = $model->setFromEntity()
        ->where("id", "=", $this->id)
        ->take(1)
        ->get()
        ->first();
        $this->data = new Fluent(json_decode($data->data));
        $this->configs = json_decode($data->configs);
        $this->progress = $data->progress;
    }


    function save() {
        $model = $this->getModel();
        $model->where("id", "=", $this->id);
        return $model->setFromEntity()->update(["progress"=>$this->progress, "response"=>$this->message]);
    }


    function isComplete($id) {
        $model = $this->getModel();
        $set = $model->setFromEntity()
        ->where("id", "=", $this->id)
        ->take(1)
        ->get()
        ->first();
        return ($set->progress == "PROCESSED" OR $set->progress == "FAILED") ? true : false;
    }


    public function processConfigs($configs) {
        $this->configs = $configs;
    }

    abstract public function process();

    abstract public function dispatch($data);

}
