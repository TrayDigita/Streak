<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler\Model;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use TrayDigita\Streak\Source\Database\Abstracts\Model;

class ActionSchedulersLog extends Model
{
    protected string $tableName = 'action_schedulers_log';

    /**
     * @var array|array[]
     */
    protected array $tableStructureData = [
        'id' => [
            'type' => BigIntType::class,
            'options' => [
                'length' => 20,
                'unsigned' => true,
                'notnull' => true,
                'comment' => 'Identifier',
                'autoIncrement' => true,
            ],
            'primary' => true,
        ],
        'callback' => [
            'type' => StringType::class,
            'options' => [
                'length' => 512,
                'notnull' => true,
                'comment' => 'Scheduler callback'
            ],
            'index' => 'callback'
        ],
        'status' => [
            'type' => StringType::class,
            'options' => [
                'length' => 120,
                'notnull' => true,
                'comment' => 'Scheduler status fail or success'
            ],
            'index' => 'status'
        ],
        'message' => [
            'type' => TextType::class,
            'options' => [
                'notnull' => true,
                'default' => '',
                'comment' => 'Scheduler message'
            ],
        ],
        'created_at' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'created_at time'
            ],
            'index' => 'created_at'
        ],
        'processed_time' => [
            'type' => FloatType::class,
            'options' => [
                'notnull' => false,
                'default' => null,
                'comment' => 'processed_time float'
            ],
            'index' => 'processed_time'
        ],
        'last_execute' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => true,
                'comment' => 'last_execute time'
            ],
            'index' => 'last_execute'
        ],
    ];

    /**
     * @param ActionSchedulers $actionSchedulers
     *
     * @return bool|int
     * @throws Exception
     */
    public static function insertFromActionScheduler(ActionSchedulers $actionSchedulers): bool|int
    {
        if (!$actionSchedulers->isFetched()) {
            return false;
        }
        $obj = new static($actionSchedulers->getContainer());
        $params = [
            'callback' => $actionSchedulers->callback,
            'status' => $actionSchedulers->status,
            'message' => $actionSchedulers->message,
            'processed_time' => $actionSchedulers->processed_time,
            'last_execute'  => $actionSchedulers->last_execute,
            'created_at' => $obj->nowDateTime(),
        ];
        return $obj->insert($params);
    }
}
