<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Models\Schema;

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use TrayDigita\Streak\Source\Database\Traits\ModelSchema;

trait UserMetaSchema
{
    use ModelSchema;

    /**
     * @var ?string
     */
    protected ?string $tableSchemaCollation = 'utf8mb4_unicode_ci';

    /**
     * @var array
     */
    protected array $tableSchemaData = [
        'user_id' => [
            'type' => BigIntType::class,
            'options' => [
                'length' => 20,
                'unsigned' => true,
                'notnull' => true,
                'comment' => 'users.id'
            ],
            'foreign' => [
                'name' => 'user_meta_relation_users_id',
                'table' => 'users',
                'column' => 'id',
                'options' => [
                    'onDelete' => 'CASCADE',
                    'onUpdate' => 'CASCADE',
                ]
            ]
        ],
        'meta_name' => [
            'type' => StringType::class,
            'options' => [
                'length' => 255,
                'notnull' => true,
                'comment' => 'unique meta_name+user_id',
                // general
                'collation' => 'utf8mb4_general_ci',
            ],
            'index' => 'user_meta_index_username',
        ],
        'meta_value' => [
            'type' => TextType::class,
            'options' => [
                'length' => 4294967295,
                'notnull' => false,
                'comment' => 'meta_value for user',
                // unicode
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ],
    ];

    /**
     * @var array[]
     */
    protected array $tableSchemaIndexes = [
        [
            'type' => 'unique',
            'name' => 'meta_value_index_unique_user_id_meta_name',
            'columns' => [
                'user_id',
                'meta_name'
            ]
        ]
    ];
}
