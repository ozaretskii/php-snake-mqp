<?php

namespace Ozaretskii\PhpSnakeMqp\adapters;

use mysqli;
use Opis\Closure\SerializableClosure;
use Ozaretskii\PhpSnakeMqp\objects\JobInQueue;
use Ozaretskii\PhpSnakeMqp\SnakeClient;

class MysqlQueueAdapter implements QueueAdapterInterface
{
    /** @var mysqli */
    protected $connection;

    /**
     * @param array{host: string, user: string, password: string, database: string, port: int, socket: int} $args
     */
    public function __construct($args)
    {
        $this->connection = new mysqli(
            isset($args['host']) ? $args['host'] : null,
            isset($args['user']) ? $args['user'] : null,
            isset($args['password']) ? $args['password'] : null,
            isset($args['database']) ? $args['database'] : null,
            isset($args['port']) ? $args['port'] : null,
            isset($args['socket']) ? $args['socket'] : null
        );
    }

    public function addQueue($jobClassName, $attributes = [], $systemAttributes = [], $priority = 1024, $delay = null, $queue = 'default')
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO `task_queue`(job_classname, arguments, system_arguments, priority, delay, status, queue, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(NOW()))"
        );
        $stmt->bind_param(
            'sssiiis',
            ...[
                static::encodeArgs($jobClassName),
                // @todo do not encode to base64, because it has max string size limit
                static::encodeArgs($attributes),
                static::encodeArgs($systemAttributes),
                $priority,
                intval($delay),
                SnakeClient::STATUS_PENDING,
                $queue
            ]
        );
        return $stmt->execute() ? $stmt->insert_id : false;
    }

    public function status($messageId)
    {
        $stmt = $this->connection->prepare("SELECT status FROM `task_queue` WHERE id=?");
        $stmt->bind_param('i', $messageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        return $data ? $data['id'] : false;
    }

    public function remove($messageId)
    {
        $stmt = $this->connection->prepare("DELETE FROM `task_queue` WHERE id=?");
        $stmt->bind_param('i', $messageId);
        $stmt->execute();

        return $stmt->field_count;
    }

    public function clear()
    {
        return $this->connection->prepare("DELETE FROM `task_queue`")->execute();
    }

    public function getMessagesFromQueue($count = 1, $maxPriority = null, $jobClassName = null)
    {
        return static::prepareRows($this->fetchMessagesFromQueue($count, '*', $maxPriority, $jobClassName));
    }

    public function getMessagesForProcessAndLock($count = 1, $maxPriority = null, $jobClassName = null)
    {
        $this->connection->begin_transaction();
        $rawData = $this->fetchMessagesFromQueue($count, '*', $maxPriority, $jobClassName, true);
        $ids = array_column($rawData, 'id');
        if (empty($ids)) {
            $this->connection->rollback();
            return [];
        }

        $inQuery = implode(',', $ids);
        $stmt = $this->connection->prepare("UPDATE `task_queue` SET status=? WHERE id IN ($inQuery)");
        $stmt->bind_param('i', ...[SnakeClient::STATUS_RESERVED]);
        $stmt->execute();
        $this->connection->commit();

        return static::prepareRows($rawData);
    }


    public function updateMessageStatus($jobId, $status, $result = null)
    {
        $sql = 'UPDATE `task_queue` SET status=?';
        $paramsType = 'i';
        $params = [$status];
        if ($result !== null) {
            $sql .= ', result=?';
            $paramsType .= 's';
            $params[] = serialize($result);
        }

        switch ($status) {
            case SnakeClient::STATUS_STARTED:
                $sql .= ', started_at=UNIX_TIMESTAMP(NOW())';
                break;
            case SnakeClient::STATUS_SUCCESS:
            case SnakeClient::STATUS_FAILURE:
            case SnakeClient::STATUS_RETRY:
                $sql .= ', finished_at=UNIX_TIMESTAMP(NOW())';
                break;
        }

        $sql .= ' WHERE id=?';
        $paramsType .= 'i';
        $params[] = $jobId;
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param($paramsType, ...$params);

        return $stmt->execute();
    }

    public function getJobsWithStatus($status)
    {
        $sql = "SELECT * FROM `task_queue` WHERE status=?";
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('i', $status);
        $stmt->execute();
        $result = $stmt->get_result();
        $rawData = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $rawData[] = $row;
        }
        $result->free();

        return static::prepareRows($rawData);
    }

    public function countJobsWithStatus($status)
    {
        return $this->connection
            ->query("SELECT COUNT(*) FROM `task_queue`")
            ->fetch_row()[0];
    }

    public function resetStuckJobs($period = 0)
    {
        $stmt = $this->connection->prepare('UPDATE `task_queue` SET `status`=? WHERE UNIX_TIMESTAMP(NOW()) >= (? + `created_at`) AND `status`=:?');
        $stmt->bind_param(
            'iii',
            ...[
                SnakeClient::STATUS_RESERVED,
                $period,
                SnakeClient::STATUS_PENDING
            ]
        );
        $stmt->execute();

        return $stmt->field_count;
    }

    protected static function encodeArgs($args)
    {
        if ($args instanceof \Closure) {
            $args = new SerializableClosure($args);
        }

        return base64_encode(serialize($args));
    }

    protected static function decodeArgs($value)
    {
        $data = unserialize(base64_encode($value));
        if ($data instanceof SerializableClosure) {
            return $data->getClosure();
        }

        return $data;
    }

    /**
     * @param array $row
     * @return JobInQueue
     */
    protected static function prepareRow($row)
    {
        $obj = new JobInQueue();
        $obj->id = $row['id'];
        $obj->status = $row['status'];
        $obj->className = static::decodeArgs($row['job_classname']);
        $obj->arguments = static::decodeArgs($row['arguments']);
        $obj->systemArguments = static::decodeArgs($row['system_arguments']);
        $obj->priority = $row['priority'];
        $obj->queue = $row['queue'];
        $obj->delay = $row['delay'];
        $obj->result = $row['result'] ? unserialize($row['result']) : null;
        $obj->createdAt = $row['created_at'];
        $obj->startedAt = $row['started_at'];
        $obj->finishedAt = $row['finished_at'];

        return $obj;
    }

    /**
     * @param $rows
     * @return JobInQueue[]
     */
    protected static function prepareRows($rows)
    {
        return array_map('static::prepareRow', $rows);
    }

    private function fetchMessagesFromQueue($count, $select = '*', $maxPriority = null, $jobClassName = null, $selectForUpdate = false)
    {
        $sql = "SELECT $select FROM `task_queue`
          WHERE ( (`status`=?) or (`status`=?) ) AND ( (`delay` is null) or (UNIX_TIMESTAMP(NOW()) >= (`delay` + `created_at`)) )";
        $paramsType = 'ii';
        $params = [
            SnakeClient::STATUS_PENDING,
            SnakeClient::STATUS_RETRY
        ];

        if ($maxPriority) {
            $sql .= ' AND `priority` <= ?';
            $paramsType .= 'i';
            $params[] = $maxPriority;
        }

        if ($jobClassName) {
            $sql .= ' AND `job_classname` LIKE ?';
            $paramsType .= 's';
            $params[] = $jobClassName;
        }

        $sql .= ' ORDER BY priority ASC, id ASC';

        if ($count) {
            $sql .= ' LIMIT ' . $count;
        }
        if ($selectForUpdate) {
            $sql .= ' FOR UPDATE;';
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param($paramsType, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $rawData = [];
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $rawData[] = $row;
        }
        $result->free();

        return $rawData;
    }

    /**
     * Creating a system table inside working database
     *
     * @return bool
     */
    public function prepare()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `task_queue` (
          `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `job_classname` text DEFAULT NULL,
          `arguments` longtext DEFAULT NULL,
          `system_arguments` longtext DEFAULT NULL,
          `priority` int(11) DEFAULT NULL,
          `delay` int(11) DEFAULT NULL,
          `status` int(11) DEFAULT NULL,
          `result` longtext DEFAULT NULL,
          `queue` varchar(100) DEFAULT NULL,
          `created_at` int(11) DEFAULT NULL,
          `started_at` int(11) DEFAULT NULL,
          `finished_at` int(11) DEFAULT NULL,

          PRIMARY KEY (`id`),
          KEY `queue` (`queue`),
          KEY `priority` (`priority`),
          KEY `status` (`status`),
          KEY `created_at` (`created_at`)
        )";
        return (bool)$this->connection->query($sql);
    }
}
