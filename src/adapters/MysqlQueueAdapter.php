<?php

namespace Ozaretskii\PhpSnakeMqp\adapters;

use mysqli;
use Opis\Closure\SerializableClosure;
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
            $args['host'],
            $args['user'],
            $args['password'],
            $args['database'],
            $args['port'],
            $args['socket']
        );
    }

    public function addQueue($jobClassName, array $attributes = [], $priority = 1024, $delay = null, $queue = 'default')
    {
        $stmt = $this->connection->prepare(
            "INSERT INTO `task_queue`(job_classname, arguments, system_arguments, priority, delay, status, queue, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(NOW()))"
        );
        $stmt->bind_param(
            'sssiiis',
            ...[
                static::encodeArgs($jobClassName),
                static::encodeArgs($attributes),
                static::encodeArgs($jobClassName),
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


    public function updateMessageStatus($jobId, $status, $result = false)
    {
        $sql = 'UPDATE `task_queue` SET status=?';
        $paramsType = 'i';
        $params = [$status];
        if ($result !== false) {
            $sql .= ', result=?';
            $paramsType .= 's';
            $params[] = $result;
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

    protected static function prepareRow($row)
    {
        $row['job_classname'] = static::decodeArgs($row['job_classname']);
        $row['system_arguments'] = static::decodeArgs($row['system_arguments']);
        $row['arguments'] = static::decodeArgs($row['arguments']);

        return $row;
    }

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
}
