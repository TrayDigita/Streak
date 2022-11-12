<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Models\Schema;

use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use TrayDigita\Streak\Source\Database\Traits\ModelSchema;

trait ActionSchedulerSchema
{
    use ModelSchema;

    /**
     * @var ?string
     */
    protected ?string $tableSchemaCollation = 'utf8mb4_unicode_ci';

    /**
     * @var array|array[]
     */
    protected array $tableSchemaData = [
        'callback' => [
            'type' => StringType::class,
            'options' => [
                'length' => 512,
                'notnull' => true,
                'comment' => 'Scheduler callback',
                'collation' => 'utf8mb4_unicode_ci'
            ],
            'index' => 'scheduler_index_callback',
            'unique' => 'scheduler_index_callback',
            'primary' => true
        ],
        'status' => [
            'type' => StringType::class,
            'options' => [
                'length' => 120,
                'notnull' => true,
                'comment' => 'Scheduler status',
                'collation' => 'utf8mb4_general_ci',
                'default' => 'pending'
            ],
            'index' => 'scheduler_index_status'
        ],
        'message' => [
            'type' => TextType::class,
            'options' => [
                'length'  => 65535,
                'notnull' => true,
                'default' => '',
                'collation' => 'utf8mb4_unicode_ci',
                'comment' => 'Scheduler message'
            ],
        ],
        'processed_time' => [
            'type' => FloatType::class,
            'options' => [
                'notnull' => false,
                'default' => null,
                'comment' => 'processed_time float'
            ],
            'index' => 'scheduler_index_processed_time'
        ],
        'created_at' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'created_at time'
            ],
            'index' => 'scheduler_index_created_at'
        ],
        'last_execute' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => false,
                'default' => "1970-01-01 00:00:00",
                'onUpdate' => 'current_timestamp',
                'comment' => 'last_execute time'
            ],
            'index' => 'scheduler_index_last_execute'
        ],
    ];
}
