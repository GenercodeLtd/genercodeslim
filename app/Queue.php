<?php

namespace GenerCodeSlim;

use Illuminate\Container\Container;
use Illuminate\Support\Fluent;

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

class Queue
{
 
    protected array $env;
    protected $config_file;
    protected array $stack = [];
    protected $aws_config;
    protected $queue;

    public function __construct(array $env, $config_file)
    {
        $this->env = $env;
        $this->config_file = $config_file;
    }

    public function __set($key, $val)
    {
        if (property_exists($this, $key)) {
            $this->$key = $val;
        }
    }

    public function __get($key)
    {
        if (property_exists($this, $key)) {
            return $this->$key;
        }
    }

    public function addMiddleware($arr)
    {
        $this->stack[] = $arr;
    }


    public function next(Container $container, Job $job)
    {
        if (count($this->stack) > 0) {
            $val = array_unshift($this->stack);
            return $val($this, $container, $job);
        } else {
            return $job->process($container);
        }
    }


    public function process($name, $id, $configs)
    {
        $container = new GenerCodeContainer();
        $configs = $container->loadConfigs($this->env, $this->config_file);

        foreach($job->configs as $key=>$config) {
            $configs[$key] = $config;
        }

        $container->addDependencies($configs);
        
        $job = $container->get($name);
        $job->id = $id;
        $job->load();

    

        $job->progress = "PROCESSING";
        $job->save();
        try {
            $this->next($container, $job);
            $job->progress = "PROCESSED";
            $job->save();
        } catch(\Exception $e) {
            $job->progress = "FAILED";
            $job->message = $e->getMessage();
            $job->save();
        }
    }


    public function runner()
    {
        $client = new SqsClient($this->aws_config);
        while (1) {
            try {
                $result = $client->receiveMessage(array(
                'AttributeNames' => ['SentTimestamp'],
                'MaxNumberOfMessages' => 1,
                'MessageAttributeNames' => ['All'],
                'QueueUrl' => $this->queue, // REQUIRED
                'WaitTimeSeconds' => 20,
                ));

                if (!empty($result->get('Messages'))) {
                    $msg = $result->get('Messages')[0];

                    $message = json_decode($msg["Body"]);

                    $this->process($messge->name, $message->id, $message->configs);

                    $result = $this->client->deleteMessage([
                        'QueueUrl' => $this->queue, // REQUIRED
                        'ReceiptHandle' => $msg['ReceiptHandle'] // REQUIRED
                        ]);
                    
                }
            } catch (AwsException $e) {
                //going to log that the process has executed and an error has happened,
                //next time cron checks, it can test for this.
                if ($logger) {
                    $logger->critical("Queue failed: " . $e->getMessage());
                } else {
                    file_put_contents("/tmp/failed.txt", $e->getMessage());
                    $fp = fopen("/tmp/log.txt", 'a');
                    fwrite($fp, date('Y-m-d H:i:s') . " " . $e->getMessage() . "\n");
                    fclose($fp);
                }
            }
        }
    }
}
