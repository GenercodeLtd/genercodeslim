<?php

namespace GenerCodeSlim;

use Illuminate\Container\Container;
use Illuminate\Support\Fluent;

use Aws\Sqs\SqsClient;
use Aws\Exception\AwsException;

class Queue
{
 
    protected $aws_config;
    protected $queue;
    protected $app;
    protected $profile_factory;

    public function __construct(Container $app, $profile_factory)
    {
        $this->app = $app;
        $this->queue = $this->app->config->get('queue.sqsarn');
        $this->aws_config = ["region" => $this->app->config->get('queue.region'), "version"=>"latest"];
        $this->profile_factory = $profile_factory;

        $factory = new \PressToJam\EntityFactory();
        $this->app->instance("entity_factory", $factory);
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

    public function processUser($configs) {
        $profile = ($this->profile_factory)($configs->name);
        $profile->id = $configs->id;
        $this->app->instance("profile", $profile);
    }


    public function process($name, $job_id, $profile)
    {
        
        $this->processUser($profile);
        $job = new $name($this->app);
        $job->id = $job_id;

        $job->load();


        
        $job->progress = "PROCESSING";
        $job->save();
        try {
            $job->process();
            $job->progress = "PROCESSED";
            $job->save();
        } catch(\Exception $e) {
            $job->progress = "FAILED";
            $job->message = $e->getMessage() . " at " . $e->getFile() . ": " . $e->getLine();
            $job->save();
        }
    }


    public function runner($logger)
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

                    //delete from queue as soon as it arrived
                    $result = $client->deleteMessage([
                        'QueueUrl' => $this->queue, // REQUIRED
                        'ReceiptHandle' => $msg['ReceiptHandle'] // REQUIRED
                        ]);

                    $message = json_decode($msg["Body"]);
                    $this->process($message->name, $message->id, $message->profile);
                    
                }
            } catch (\Exception $e) {
                //going to log that the process has executed and an error has happened,
                //next time cron checks, it can test for this.
                if ($logger) {
                    $logger->critical("Queue failed: " . $e->getMessage() . " File: " . $e->getFile() . " " . $e->getLine());
                } else {
                    file_put_contents("/tmp/failed.txt", $e->getMessage());
                    $fp = fopen(__DIR__ . "/log.txt", 'a');
                    fwrite($fp, date('Y-m-d H:i:s') . " " . $e->getMessage() . "\n");
                    fclose($fp);
                }
            }
        }
    }
}
