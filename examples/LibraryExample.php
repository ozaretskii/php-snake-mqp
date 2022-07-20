<?php

namespace Ozaretskii\Examples;

use Ozaretskii\PhpSnakeMqp\adapters\MysqlQueueAdapter;
use Ozaretskii\PhpSnakeMqp\SnakeClient;

class LibraryExample
{
    public function run()
    {
        $adapter = new MysqlQueueAdapter([
            'host' => 'localhost',
            'user' => 'root',
            'password' => 'root',
            'database' => 'test_database',
            'port' => '',
            'socket' => '',
        ]);
        // create table if not existing yet.
        $adapter->prepare();

        // Placing task into the queue
        $client = new SnakeClient($adapter);
        $client->addQueue(
            [self::class, 'testRun'],
            ['printed data', 'fn result']
        );

        foreach ($client->runJobs() as $job) {
            if ($job->status === SnakeClient::STATUS_SUCCESS) {
                var_dump("Job $job->id has finished with results: " . serialize($job->result));
            } else {
                var_dump("Job $job->id has failed with an error: " . $job->result);
            }
        }
    }

    public static function testRun($var1, $var2)
    {
        var_dump($var1);
        return $var2;
    }
}
