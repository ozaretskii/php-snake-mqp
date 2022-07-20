<?php

namespace Ozaretskii\Examples;

use Ozaretskii\PhpSnakeMqp\adapters\MysqlQueueAdapter;
use Ozaretskii\PhpSnakeMqp\SnakeClient;

class LibraryExample
{
    protected static function getClient()
    {
        $adapter = new MysqlQueueAdapter([
            'host' => 'localhost',
            'user' => 'root',
            'password' => 'root',
            'database' => 'test_database',
            'port' => '',
            'socket' => '',
        ]);
        // create table if not existing yet (can be called just once per lifetime).
        $adapter->prepare();

        // Placing task into the queue
        return new SnakeClient($adapter);
    }
    public function addMessageInQueue()
    {
        // Placing task into the queue
        $client = static::getClient();
        $client->addQueue(
            [self::class, 'testExecution'],
            ['function output', 'function result']
        );

        foreach ($client->processJobsInQueue() as $job) {
            if ($job->isSuccessful()) {
                var_dump("Job $job->id has finished.");
                var_dump("Results: ", $job->result);
                var_dump("Output: ", $job->printedOutput);
            } else {
                var_dump("Job $job->id has failed with an error.");
                var_dump($job->result);
            }
        }
    }

    public function processMessageInQueue()
    {
        // Placing task into the queue
        $client = static::getClient();
        // process 10 jobs from all queues
        $allJobs = $client->processJobsInQueue(10);
        // process 10 jobs from queue 'critical' with more priority than 1024
        $priorityJobs = $client->processJobsInQueue(10, 1024, 'critical');
        // print results
        array_map(function ($job) {
            $message = $job->isSuccessful() ? ' has finished successfully' : ' has Failed!';
            var_dump("Job [$job->id]" . $message);
        }, array_merge($priorityJobs, $allJobs));
    }

    public static function testExecution($var1, $var2)
    {
        var_dump($var1);
        return $var2;
    }
}
