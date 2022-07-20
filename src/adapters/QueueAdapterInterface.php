<?php

namespace Ozaretskii\PhpSnakeMqp\adapters;

interface QueueAdapterInterface
{
    /**
     * Add job to queue.
     *
     * @param string|array $jobClassName Class name of job
     * @param array $attributes Attributes to make job context
     * @param int $priority Priority of execution
     * @param int|null $delay Delay in seconds
     * @param string $queue Queue name
     *
     * @return string|false Message ID
     */
    public function addQueue($jobClassName, array $attributes = [], $priority = 1024, $delay = null, $queue = 'default');

    /**
     * Return status of message by ID.
     *
     * @param string $messageId
     *
     * @return int|null
     */
    public function status($messageId);

    /**
     * Remove message by ID.
     *
     * @param string $messageId
     *
     * @return bool
     */
    public function remove($messageId);

    /**
     * Clears the queue.
     *
     * @return bool
     */
    public function clear();

    /**
     * Get messages from queue
     *
     * @param int $count
     * @param int $maxPriority
     * @param string $jobClassName
     *
     * @return mixed
     */
    public function getMessagesFromQueue($count = 1, $maxPriority = null, $jobClassName = null);

    /**
     * Get messages from queue and lock them for future execution and update
     *
     * @param $count
     * @param $maxPriority
     * @param $jobClassName
     * @return mixed
     */
    public function getMessagesForProcessAndLock($count = 1, $maxPriority = null, $jobClassName = null);

    /**
     * Update message status in queue
     *
     * @param      $jobId
     * @param      $status
     * @param bool $result
     *
     * @return mixed
     */
    public function updateMessageStatus($jobId, $status, $result = false);

    /**
     * @param integer $status
     *
     * @return array
     */
    public function getJobsWithStatus($status);

    /**
     * @param integer $status
     *
     * @return integer
     */
    public function countJobsWithStatus($status);

    /**
     * Release statuses for old jobs that are being stuck
     *
     * @param $period
     * @return mixed
     */
    public function resetStuckJobs($period = 0);
}
