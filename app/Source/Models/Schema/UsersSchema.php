<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Models\Schema;

use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\StringType;
use TrayDigita\Streak\Source\Database\Traits\ModelSchema;

trait UsersSchema
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
        'id' => [
            'type' => BigIntType::class,
            'options' => [
                'length' => 20,
                'unsigned' => true,
                'notnull' => true,
                'comment' => 'identifier auto increment',
                'autoIncrement' => true,
            ],
            'primary' => true,
        ],
        /**
         * Follow standard username, only contains alpha-numeric & underscore characters
         * Username must be started with alpha characters.
         * Must be case-insensitive as unique
         *
         * Minimum: 3
         * Maximum: 64
         * Regex: [a-z][a-z0-9_]{2}(?:[a-z0-9_]{1,61})?
         */
        'username' => [
            'type' => StringType::class,
            'options' => [
                'length' => 64,
                'notnull' => true,
                'comment' => 'unique username 64 chars',
                // general
                'collation' => 'utf8mb4_general_ci',
            ],
            'index' => 'users_index_username',
            'unique' => 'users_index_username',
        ],
        /**
         * @reference https://www.ietf.org/rfc/rfc2821.txt
         * Domain: 255
         * Identifier @ : 1
         * local-part: 64
         */
        'email' => [
            'type' => StringType::class,
            'options' => [
                'length' => 320,
                'notnull' => true,
                'comment' => 'unique user email',
                // unicode
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'index' => 'users_index_email',
            'unique' => 'users_index_email',
        ],
        'password' => [
            'type' => StringType::class,
            'options' => [
                'length' => 120,
                'notnull' => true,
                'comment' => 'bcrypt[cost=10]',
                // general
                'collation' => 'utf8mb4_general_ci',
            ],
        ],
        'first_name' => [
            'type' => StringType::class,
            'options' => [
                'length' => 64,
                'notnull' => true,
                'comment' => 'user first name',
                // unicode
                'collation' => 'utf8mb4_unicode_ci',
            ],
            'index' => 'users_index_first_name',
        ],
        'last_name' => [
            'type' => StringType::class,
            'options' => [
                'length' => 64,
                'notnull' => false,
                'comment' => 'user last name nullable',
                // unicode
                'collation' => 'utf8mb4_unicode_ci',
            ],
        ],
        'created_at' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'created_at time'
            ],
            'index' => 'users_index_created_at'
        ],
        'updated_at' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => true,
                'comment' => 'created_at time',
                'default' => "1970-01-01 00:00:00",
                'onUpdate' => 'current_timestamp',
            ],
            'index' => 'users_index_updated_at'
        ],
    ];
}
