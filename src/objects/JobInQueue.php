<?php

namespace Ozaretskii\PhpSnakeMqp\objects;

use Ozaretskii\PhpSnakeMqp\SnakeClient;

class JobInQueue
{
    public $id;
    public $className;
    public $arguments;
    public $systemArguments;
    public $priority;
    public $status;
    public $queue;
    public $createdAt;
    public $delay = null;
    public $printedOutput = null;
    public $result = null;
    public $startedAt = null;
    public $finishedAt = null;

    public function isSuccessful()
    {
        return $this->status === SnakeClient::STATUS_SUCCESS;
    }

    public function isFailed()
    {
        return $this->status === SnakeClient::STATUS_FAILURE;
    }
}
