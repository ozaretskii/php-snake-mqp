<?php

namespace Ozaretskii\PhpSnakeMqp;

use Ozaretskii\PhpSnakeMqp\adapters\QueueAdapterInterface;

class SnakeClient
{
    const STATUS_PENDING = 1;
    const STATUS_RESERVED = 2;
    const STATUS_STARTED = 3;
    const STATUS_FAILURE = 4;
    const STATUS_RETRY = 5;
    const STATUS_SUCCESS = 6;
    // @todo implement later
    // const STATUS_PAUSED = 7;

    /** @var QueueAdapterInterface  */
    protected $adapter;

    public function __construct(QueueAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Placing a job into the queue.
     *
     * @param string|array $jobClassName Class name of job
     * @param array $attributes Attributes to make job context
     * @param int $priority Priority of execution
     * @param int|null $delay Delay in seconds
     * @param string $queue Queue name
     *
     * @return false|string Message ID
     */
    public function addQueue(
        $jobClassName,
        $attributes = [],
        $priority = 1024,
        $delay = null,
        $queue = 'default'
    ) {
        return $this->adapter->addQueue($jobClassName, $attributes, $priority, $delay, $queue);
    }


    /**
     * Return status of message by ID.
     *
     * @param string $messageId
     *
     * @return int|null
     */
    public function status($messageId)
    {
        return $this->adapter->status($messageId);
    }


    /**
     * Get messages from queue (without locking their state)
     *
     * @param int $count
     * @param string|null $jobClassName
     * @param int|null $maxPriority
     *
     * @return mixed
     */
    public function getMessagesFromQueue($count = 1, $maxPriority = null, $jobClassName = null)
    {
        return $this->adapter->getMessagesFromQueue($count, $maxPriority, $jobClassName);
    }

    /**
     * Lock messages and fetch for future processing.
     * Messages are required to be updated further.
     *
     * @param int $count
     * @param string|null $jobClassName
     * @param int|null $maxPriority
     *
     * @return mixed
     */
    public function getMessagesForProcessAndLock($count = 1, $maxPriority = null, $jobClassName = null)
    {
        return $this->adapter->getMessagesForProcessAndLock($count, $maxPriority, $jobClassName);
    }


    /**
     * Remove message by ID.
     *
     * @param string $messageId
     *
     * @return bool
     */
    public function remove($messageId)
    {
        return $this->adapter->remove($messageId);
    }


    /**
     * Clears the queue.
     *
     * @return bool
     */
    public function clear()
    {
        return $this->adapter->clear();
    }


    /**
     * Get all jobs with specific status
     *
     * @param $status
     *
     * @return array
     */
    public function getJobsWithStatus($status)
    {
        return $this->adapter->getJobsWithStatus($status);
    }


    /**
     * Get count of job with specific status
     *
     * @param $status
     *
     * @return int
     */
    public function getCountJobsWithStatus($status)
    {
        return $this->adapter->countJobsWithStatus($status);
    }
}
