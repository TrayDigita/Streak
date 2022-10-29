<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Models\Schema;

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use TrayDigita\Streak\Source\Database\Traits\ModelSchema;

trait ActionSchedulersLogSchema
{
    use ModelSchema;

    protected ?string $tableSchemaCollation = 'utf8mb4_unicode_ci';

    /**
     * @var array|array[]
     */
    protected array $tableSchemaData = [
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
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => 'Scheduler callback'
            ],
            'index' => 'scheduler_log_index_callback'
        ],
        'status' => [
            'type' => StringType::class,
            'options' => [
                'length' => 120,
                'notnull' => true,
                'comment' => 'Scheduler status fail or success'
            ],
            'index' => 'scheduler_log_index_status'
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
            'index' => 'scheduler_log_index_created_at'
        ],
        'processed_time' => [
            'type' => FloatType::class,
            'options' => [
                'notnull' => false,
                'default' => null,
                'comment' => 'processed_time float'
            ],
            'index' => 'scheduler_log_index_processed_time'
        ],
        'last_execute' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => true,
                'comment' => 'last_execute time'
            ],
            'index' => 'scheduler_log_index_last_execute'
        ],
    ];
}
