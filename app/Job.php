<?php

namespace GenerCodeSlim;

use \Illuminate\Container\Container;
use \Illuminate\Support\Fluent;

use Aws\Sqs\SqsClient; 
use Aws\Exception\AwsException;

use \GenerCodeOrm\Model;
use \GenerCodeOrm\Repository;
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
   
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->profile = $app->get(\GenerCodeOrm\Profile::class);
        $this->queue = $this->app->config['queue']["sqsarn"];
        $this->aws_config = ["region" => $this->app->config['queue']["region"], "version"=>"latest"];
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


    function addToQueue() {
        $name = get_class($this);
        $model = $this->app->get(Model::class);
        $model->name = "queue";
        $model->data = [
            "user-login-id"=>$this->profile->id,
            "name"=>$name,
            "data"=>json_encode($this->data),
            "configs"=>json_encode($this->configs),
            "progress"=>"PENDING"
        ];
        $arr = $model->create();

        $client = $this->createClient();

        $configs = ["configs"=>$this->configs, "profile" => ["name"=>$this->profile->name, "id"=>$this->profile->id]];
        $params = [
            'DelaySeconds' => 10,
            'MessageBody' => json_encode(["id"=>$arr["--id"], "name"=>$name, "configs"=>$configs]),
            'QueueUrl' => $this->queue
        ];

        $client->sendMessage($params);
       
        return $arr["--id"];
    }

    function load() {
        $model = $this->app->get(Repository::class);
        $model->name = "queue";
        $model->where = ["--id"=>$this->id];
        $data = $model->get();
        $this->data = new Fluent(json_decode($data->data));
        $this->configs = $data->configs;
        $this->progress = $data->progress;
    }


    function save() {
        $model = $this->app->get(Model::class);
        $model->name = "queue";
        $model->where = ["--id"=>$this->id];
        $model->data = ["progress"=>$this->progress, "response"=>$this->message];
        $res = $model->update();
        return $res["original"];
    }



    function processFeedback($id) {
        $data = $this->getFromQueue($id);
        $model = $this->app->get(Model::class);
        $model->name = "queue";
        $model->where = ["--id"=>$id];
        $res = $model->delete();
        return $res["original"];
    }


    function isComplete($id) {
        $model = $this->app->get(Model::class);
        $model->name = "queue";
        $dataSet = $model->createDataSet(["--id"=>$id]);
        $set = $dataSet->select($dataSet);
        return ($set->progress == "PROCESSED" OR $set->progress == "FAILED") ? true : false;
    }


    public function processConfigs($configs) {
        $this->configs = $configs;
    }

    abstract public function process();

    abstract public function dispatch($data);

}
