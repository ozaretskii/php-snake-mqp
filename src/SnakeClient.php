<?php

namespace Ozaretskii\PhpSnakeMqp;

use Ozaretskii\PhpSnakeMqp\adapters\QueueAdapterInterface;
use Ozaretskii\PhpSnakeMqp\objects\JobInQueue;

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

    /** @var QueueAdapterInterface */
    protected $adapter;

    public function __construct(QueueAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Placing a job into the queue.
     *
     * @param callable $jobClassName Class name of job
     * @param array $attributes Attributes to make job context
     * @param array $systemAttributes Reserved for extending by each specific framework needs
     * @param int $priority Priority of execution
     * @param int|null $delay Delay in seconds
     * @param string $queue Queue name
     *
     * @return false|string Message ID
     */
    public function addQueue(
        $jobClassName,
        $attributes = [],
        $systemAttributes = [],
        $priority = 1024,
        $delay = null,
        $queue = 'default'
    )
    {
        return $this->adapter->addQueue($jobClassName, $attributes, $systemAttributes, $priority, $delay, $queue);
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
     * @return JobInQueue[]
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
     * @return JobInQueue[]
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
     * @return JobInQueue[]
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

    /**
     * CLI daemon that is listening the queue and processing tasks when they are presented
     *
     * @param $sleepWhenNoJobs
     * @param $maxPriority
     * @param $output
     * @return mixed
     */
    public function runCliJobsDaemon($sleepWhenNoJobs = 15, $maxPriority = null, $output = true)
    {
        while (true) {
            // execute task from queue
            $jobs = $this->processJobsInQueue(1, $maxPriority);
            if ($output) {
                // print output if necessary
                array_map(function ($job) {
                    $message = $job->isSuccessful() ? ' has finished successfully' : ' has Failed!';
                    var_dump("Job [$job->id]" . $message);
                }, $jobs);
            }
            if (
                count($jobs) <= 0
                && $sleepWhenNoJobs
            ) {
                // sleep in case of no active jobs to prevent unnecessary high load on hardware
                sleep($sleepWhenNoJobs);
            }
        }
    }

    /**
     * Pick messages from queue and process them
     *
     * @param $count
     * @param $maxPriority
     * @return JobInQueue[]
     */
    public function processJobsInQueue($count = 1, $maxPriority = null)
    {
        $result = [];
        foreach ($this->adapter->getMessagesForProcessAndLock($count, $maxPriority) as $job) {
            $result[$job->id] = $this->processJobInQueue($job);
        }

        return $result;
    }

    /**
     * @param JobInQueue $job
     * @return JobInQueue
     */
    protected function processJobInQueue(JobInQueue $job)
    {
        // support of PHP >= 7.0
        if (interface_exists('Throwable', false)) {
            try {
                $job->status = static::STATUS_SUCCESS;
                $job->result = $this->executeJob($job);
            } catch (\Throwable $e) {
                $this->adapter->updateMessageStatus($job->id, static::STATUS_FAILURE, $e);
                $job->status = static::STATUS_FAILURE;
                $job->result = $e;
            }
        } else {
            try {
                $job->status = static::STATUS_SUCCESS;
                $job->result = $this->executeJob($job);
            } catch (\Exception $e) {
                $this->adapter->updateMessageStatus($job->id, static::STATUS_FAILURE, $e);
                $job->status = static::STATUS_FAILURE;
                $job->result = $e;
            }
        }

        return $job;
    }

    /**
     * May be overridden to cover extended framework needs
     *
     * @param $job
     * @return mixed
     */
    protected function executeJob($job)
    {
        $this->adapter->updateMessageStatus($job->id, static::STATUS_STARTED);
        $result = call_user_func_array($job->className, $job->arguments);
        $this->adapter->updateMessageStatus($job->id, static::STATUS_SUCCESS, $result);

        return $result;
    }
}
