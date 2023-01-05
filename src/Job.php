<?php

namespace GenerCodeSlim;

use \Illuminate\Container\Container;
use \Illuminate\Support\Fluent;

use Aws\Sqs\SqsClient; 
use Aws\Exception\AwsException;

use \GenerCodeOrm\Model;
use \GenerCodeOrm\Profile;

abstract class Job extends \Illuminate\Queue\Jobs\Job 
{

    protected Container $app;
    protected \GenerCodeOrm\Profile $profile;
    protected $data;
    protected $progress = "pending";
    protected $message = "";
    protected $id = 0;
    protected $is_fifo = false;
   
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->profile = new \GenerCodeOrm\Profile();
        if ($app->has("profile")) {
            $oprofile = $app->get("profile");
            $this->profile->name = $oprofile->name;
            $this->profile->id = $oprofile->id;
        }
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

    public function getJobId() {
        return $this->id;
    }

    public function getRawBody() {
        return $this->data;
    }


    function getModel() {
        return $this->app->makeWith(Model::class, ["name"=>"queue"]);
    }


    
    function __serialize() {
        $this->id = $this->save();
        return [
            "id" => $this->id,
            "user-profile"=>["name"=>$this->profile->name, "id"=>$this->profile->id]
        ];
    }


    function __unserialize($data) {
        $this->id = $data["id"];
        $this->profile->name = $data["user-profile"]["name"];
        $this->profile->id = $data["user-profile"]["id"];
    }


    function load() {
        $model = $this->getModel();
        $data = $model->setFromEntity()
        ->where("id", "=", $this->id)
        ->take(1)
        ->get()
        ->first();
        $this->data = new Fluent(json_decode($data->data));
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


    function handle() {
        $this->load();
        try {
            $this->process();
            $this->progress = "PROCESSED";
            $this->save();
        } catch(\Exception $e) {
            $this->progress = "FAILED";
            $this->messge = $e->getMessge();
            $this->save();
        }
    }


    function dispatch() {
        $model = $this->getModel();
        $root = $model->root;
        $params  = [
            "user-login-id"=>$this->profile->id,
            "name"=>$this->profile->name,
            "data"=>json_encode($this->data),
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

        $this->id = $model->setFromEntity(true)->insertGetId($data->toCellNameArr());

        $queue = $app->get("queue");
        $queue->popOn($job);
        return $this->id;
    }

    abstract function process();
}
