# PHP async messages queueing and processing library

SnakeMQP is a library for running and managing php tasks asynchronously via queues.
You can simpy integrate async execution any part of your code
with just a couple of minutes of work. This library provides
you with all necessary tools to just start enjoying aync tasks processing
out of the box, or building more complex and scalable task management system
on top of it.

Ofter our code execution require more time or resources
that we can afford on per same http request basis.
In that case moving parts of most heavy or time-consuming
operations on background can be an obvious solution. Background
execution allows you to process multiple tasks per same time by using multiple workers,
or simultaneous "chain" (placing next task to queue after current is finished) tasks execution, when an order of execution is critically important.

You can simply manage many types of your tasks by placing
them into separated queue names and assigning priorities for any specific task or a queue.
Delayed execution can also be handy in some specific cases. Dedicated workers for processing
specific queue names can allow you to immediately process important tasks
besides other queues to make sure that critically important parts of your project is always running on time.

SnakeMQP allows you to make any number and type of independent "workers" 
that can be called from project code, cli or rest api. That means you can
integrate asynchronous execution of any part of your code even been limited to low level server access.
Your workers can be triggered by simple crontab script, CURL, external authorized API call or unix daemon (like Supervisor or Systemd).

## Benefits of using SnakeMQP:
- Easy integration in any php based project
- Highly manageable queueing system
- Delayed execution of tasks
- Having a dedicated workers to process specific queues or tasks with specific priority only
- Dynamic integration of "workers" to meet your project needs

## Quick Examples

### Placing tasks for further processing (mysql based queue)
```php
<?php
// Require the Composer autoloader.
require 'vendor/autoload.php';

use Ozaretskii\PhpSnakeMqp\adapters\MysqlQueueAdapter;
use Ozaretskii\PhpSnakeMqp\SnakeClient;

// Initiate queue adapter
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
// Placing tasks into the queue
$hello = function ($arg1, $arg2) {
    var_dump($arg1);
    return $arg2;
};
$client = new SnakeClient($adapter);
// queueing closure
$client->addQueue($hello, ['function output', 'function result']);
/**
 * Worker:
 * - output: string(15) "function output"
 * - result: "function result"
 */
// queueing regular function
$client->addQueue('var_dump', ['text1', ['data' => 'array']]);
/** Worker:
 * - output: 
 * string(5) "text1"
 * array(1) {
 *   ["data"]=> string(5) "array"
 * } 
 * - result: null
 */
```

### Running worker (mysql based queue)
```php
<?php
// Initiate queue adapter
$adapter = new MysqlQueueAdapter([
    'host' => 'localhost',
    'user' => 'root',
    'password' => 'root',
    'database' => 'test_database',
    'port' => '',
    'socket' => '',
]);
// process 10 jobs from the queue and print results
foreach ($client->processJobsInQueue(10) as $job) {
    if ($job->isSuccessful()) {
        var_dump("Job $job->id has finished.");
        var_dump("Results: ", $job->result);
        var_dump("Output: ", $job->printedOutput);
    } else {
        var_dump("Job $job->id has failed with an error.");
        var_dump("Error: ", $job->result);
        var_dump("Output: ", $job->printedOutput);
    }
}
```

## Getting Help

If you encounter a bug, need help with implementing SnakeMQP into your project,
or you need to request an extra functionality into the library - 
feel free submitting any issues on https://github.com/ozaretskii/php-snake-mqp/issues
. Any PR's are welcome. I hope it will help you with your needs. Thanks.
