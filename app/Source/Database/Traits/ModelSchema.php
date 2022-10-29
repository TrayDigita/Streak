<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Database\Traits;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use TrayDigita\Streak\Source\Database\Instance;
use TrayDigita\Streak\Source\Traits\TranslationMethods;
use TypeError;

trait ModelSchema
{
    use TranslationMethods;

    /**
     * @return string
     */
    abstract public function getTableName() : string;

    /**
     * @return Instance
     */
    abstract public function getDatabaseInstance() : Instance;

    /**
     * @reference
     * @return array
     * @uses \Doctrine\DBAL\Types\Types::TEXT
     *
     *     TINYTEXT   : 2 ^  8 - 1 = 255
     *     TEXT       : 2 ^ 16 - 1 = 65535
     *     MEDIUMTEXT : 2 ^ 24 - 1 = 16777215
     *     LONGTEXT   : 2 ^ 32 - 1 = 4294967295
     */
    public function getTableSchemaData() : array
    {
        if (isset($this->tableSchemaData) && is_array($this->tableSchemaData)) {
            return $this->tableSchemaData;
        }
        return [];
    }

    /**
     * Tables indexes
     *
     * @return array
     */
    public function getTableSchemaIndexes() : array
    {
        if (isset($this->tableSchemaIndexes) && is_array($this->tableSchemaIndexes)) {
            return $this->tableSchemaIndexes;
        }
        return [];
    }

    /**
     * Table collation
     *
     * @return ?string
     */
    public function getTableSchemaCollation() : ?string
    {
        if (isset($this->tableSchemaCollation) && is_string($this->tableSchemaCollation)) {
            return $this->tableSchemaCollation;
        }
        return null;
    }

    /**
     * @return Table
     * @throws Exception
     * @throws SchemaException
     */
    public function getTableFromSchemaData() : Table
    {
        $tableSchemaData = $this->getTableSchemaData();
        if (empty($tableSchemaData)) {
            return $this->getDatabaseInstance()->getTableDetails($this->getTableName());
        }

        // determine columns method set
        static $columnsMethods = null;
        if (!is_array($columnsMethods)) {
            $columnsMethods = (new ReflectionClass(Column::class))->getMethods(ReflectionMethod::IS_PUBLIC);
            $columnsMethods = array_filter(
                $columnsMethods,
                fn ($e) => ($name = strtolower($e->getName()))
                           && !str_contains($name, 'option')
                           && str_starts_with($name, 'set')
            );
            $columnsMethods = array_map(fn ($e) => strtolower(substr($e->getName(), 3)), $columnsMethods);
            $columnsMethods = array_flip($columnsMethods);
        }
        $table = new Table($this->getTableName());
        $modes = [];
        $primary = [];
        $foreign = [];
        $database = $this->getDatabaseInstance();
        $platform = $database->getDatabasePlatform();
        $supportCollation = $platform->supportsColumnCollation();
        $isMysql = is_a($platform, MySQLPlatform::class);
        $supportComment = $platform->supportsInlineColumnComments();
        $isPostgreSQL = $platform instanceof PostgreSQLPlatform;
        foreach ($tableSchemaData as $columnName => $definitions) {
            $type = $definitions['type']??null;
            if (!$type || !is_string($type)) {
                throw new TypeError(
                    sprintf(
                        $this->translate(
                            'Database type %s is not exist.'
                        ),
                        is_object($type) ? get_class($type) : (string) $type
                    )
                );
            }
            if (!Type::hasType($type)) {
                if (!is_a($type, Type::class, true)) {
                    throw new TypeError(
                        sprintf(
                            $this->translate(
                                'Database type %s is not exist.'
                            ),
                            $type
                        )
                    );
                }
                $oldType = $type;
                $type = array_search($type, Type::getTypesMap());
                if (!$type) {
                    throw new TypeError(
                        sprintf(
                            $this->translate(
                                'Database type %s is not exist.'
                            ),
                            $oldType
                        )
                    );
                }
            }

            $options = $definitions['options']??[];
            $onUpdate = null;
            $collation = null;
            // filter
            foreach ($options as $opt => $item) {
                $lower = strtolower($opt);
                if ($lower === 'onupdate') {
                    $onUpdate = $item;
                    unset($options[$opt]);
                    continue;
                }
                if ($lower === 'collation') {
                    $collation = is_string($item) ? (strtolower(trim($item))?:null) : null;
                    unset($options[$lower]);
                    continue;
                }
                if (!$supportComment && $lower === 'comment' || !isset($columnsMethods[$lower])) {
                    unset($options[$opt]);
                }
            }

            if (isset($options['default']) && is_string($options['default'])) {
                $options['default'] = match (strtolower($options['default'])) {
                    'current_timestamp' => $platform->getCurrentTimestampSQL(),
                    'current_date' => $platform->getCurrentDateSQL(),
                    'current_time' => $platform->getCurrentTimeSQL(),
                    default => $options['default']
                };
            }
            if ($isPostgreSQL
                && !empty($options['default'])
                && preg_match('~^[0]{4}-([0-9]+)-([0-9]+)\s+(.+)$~', $options['default'], $match)
                && in_array(
                    $type,
                    [
                        Types::DATE_IMMUTABLE,
                        Types::DATE_MUTABLE,
                        Types::DATETIME_IMMUTABLE,
                        Types::DATETIME_MUTABLE,
                        Types::DATETIMETZ_IMMUTABLE,
                        Types::DATETIMETZ_MUTABLE,
                    ]
                )) {
                $match[1] = (int) $match[1];
                $match[2] = (int) $match[2];
                $month = $match[1];
                $days = $match[2];
                if ($month < 1) {
                    $match[1] = '01';
                    $month = 1;
                } elseif ($month > 12) {
                    $match[1] = '12';
                    $month = 12;
                }
                if ($days < 1) {
                    $match[2] = '01';
                } else {
                    // September, April, June, and November
                    $month31 = [9, 4, 6, 11];
                    if ($days > 30) {
                        $match[2] = !in_array($month, $month31) ? 30 : 31;
                    }
                    if ($month === 2 && $days > 28) {
                        $match[2] = 28;
                    }
                }

                if (is_int($match[1])) {
                    $match[1] = $match[1] < 10 ? "0$match[1]" : $match[1];
                }
                if (is_int($match[2])) {
                    $match[2] = $match[2] < 10 ? "0$match[2]" : $match[2];
                }
                $options['default'] = sprintf('1970-%s-%s %s', $match[1], $match[2], $match[3]);
            }

            // add support collation
            $column = $table->addColumn($columnName, $type, $options);
            if ($collation && $isPostgreSQL) {
                $collation = str_starts_with($collation, 'utf8') ? 'UTF-8' : $collation;
            }

            if ($supportCollation && is_string($collation)
                && ($definitionCollate = $database->getCollationByName($collation))
                && in_array(
                    $type,
                    // allowed character set
                    [
                        Types::TEXT,
                        Types::JSON,
                        Types::BLOB,
                        Types::STRING,
                        Types::SIMPLE_ARRAY,
                        Types::ASCII_STRING,
                        Types::BINARY,
                        Types::GUID,
                    ]
                )
            ) {
                // add collation
                $column->setPlatformOption('collation', $definitionCollate['collate']);
                $column->setPlatformOption('charset', $definitionCollate['charset']);
            }

            $onUpdate = is_string($onUpdate) ? match (strtolower(trim($onUpdate))) {
                'current_timestamp' => $platform->getCurrentTimestampSQL(),
                'current_date' => $platform->getCurrentDateSQL(),
                'current_time' => $platform->getCurrentTimeSQL(),
                default => null
            } : null;
            if ($isMysql && $onUpdate !== null) {
                $columnValue = $column->toArray();
                $default = $platform->getDefaultValueDeclarationSQL($columnValue);
                $charset = ! empty($columnValue['charset'])
                    ? ' ' . $platform->getColumnCharsetDeclarationSQL($columnValue['charset'])
                    : '';
                $collation = ! empty($columnValue['collation'])
                    ? ' ' . $platform->getColumnCollationDeclarationSQL($columnValue['collation'])
                    : '';
                $notnull     = ! empty($columnValue['notnull']) ? ' NOT NULL' : '';
                $unique      = ! empty($columnValue['unique']) ? ' UNIQUE'  : '';
                $check       = ! empty($columnValue['check']) ? ' ' . $columnValue['check'] : '';
                $typeDeclaration = $columnValue['type']->getSQLDeclaration($columnValue, $platform);
                $onUpdate    = " ON UPDATE $onUpdate";
                $declaration = $typeDeclaration
                               . $charset
                               . $default
                               . $onUpdate
                               . $notnull
                               . $unique
                               . $check
                               . $collation;
                if ($supportComment && isset($columnValue['comment']) && $columnValue['comment'] !== '') {
                    $declaration .= ' ' . $platform->getInlineColumnCommentSQL($columnValue['comment']);
                }

                $column->setColumnDefinition($declaration);
            }

            $detect = [
                'unique' => 'addUniqueIndex',
                'index' => 'addIndex'
            ];
            if (!empty($definitions['primary'])) {
                $primary[$columnName] = $columnName;
            }
            if (!empty($definitions['foreign']) && is_array($definitions['foreign'])) {
                $foreigner = $definitions['foreign'];
                $tableForeign = $foreigner['table']??null;
                $columnForeign = $foreigner['column']??null;
                $foreignName = $foreigner['name']??null;
                $foreignOptions = $foreigner['options']??null;
                if ($tableForeign && $columnForeign) {
                    $foreign[$columnName] = [
                        'table' => $tableForeign,
                        'column' => $columnForeign,
                        'name' => is_string($foreignName) ? $foreignName : null,
                        'options' => is_array($foreignOptions) ? $foreignOptions : [],
                    ];
                }
            }
            foreach ($detect as $mode => $method) {
                if (!isset($definitions[$mode])) {
                    continue;
                }
                if (isset($modes[$columnName])) {
                    continue;
                }
                $indexes_name = $definitions[$mode];
                if (!is_array($indexes_name)) {
                    $modes[$columnName] = [
                        'method' => $method,
                        'name' => is_string($indexes_name) ? $indexes_name : null,
                        'columns' => [$columnName]
                    ];
                    continue;
                }
                $column = $indexes_name['columns']??null;
                $column = is_string($column) ? [$column] : (
                    is_array($column) ? $column : null
                );
                if ($columnName === null) {
                    $column = [$columnName];
                } else {
                    $column = array_merge([$columnName], $column);
                }
                $column = array_values(array_filter(array_unique($column)));
                $name  = $indexes_name['name']??null;
                $modes[$columnName] = [
                    'method' => $method,
                    'name' => $name,
                    'columns' => $column
                ];
            }
        }

        $columnsName = array_keys($table->getColumns());
        foreach ($modes as $columnName => $options) {
            $method = $options['method'];
            if (isset($primary[$columnName]) && $options['method'] === 'addUniqueIndex') {
                continue;
            }
            $diff = array_diff($options['columns'], $columnsName);
            if (!empty($diff)) {
                throw new RuntimeException(
                    sprintf(
                        $this->translate('Columns %s is not exists on index %s column %s'),
                        implode(', ', $diff),
                        $options['name'],
                        $columnName
                    )
                );
            }
            $table->$method($options['columns'], $options['name']);
        }
        if (!empty($primary)) {
            $table->setPrimaryKey($primary);
        }
        $indexes = $this->getTableSchemaIndexes();
        foreach ($indexes as $item) {
            if (!is_array($item)
                || !isset($item['type'], $item['columns'])
                || !in_array($item['type'], ['unique', 'index'])
            ) {
                continue;
            }
            $oldColumn = $item['columns'];
            $oldColumn = is_string($oldColumn) ? [$oldColumn] : $oldColumn;
            if (!is_array($oldColumn)) {
                continue;
            }
            $columns = array_filter($oldColumn, 'is_string');
            $continue = true;
            foreach ($columns as $col) {
                if (!$table->hasColumn($col)) {
                    $continue = false;
                    break;
                }
            }

            if (!$continue || count($columns) < count($oldColumn)) {
                continue;
            }
            $name = $item['name']??null;
            if ($name !== null && !is_string($name)) {
                continue;
            }
            if ($isPostgreSQL && in_array($name, $columns)) {
                $name = null;
            }
            if ($name !== null && $table->hasIndex($name)) {
                continue;
            }
            $type = $item['type'];
            $method = $type === 'index' ? 'addIndex' : 'addUniqueIndex';
            $table->$method($columns, $name);
        }
        $prefix = $database->prefix;
        if (!empty($foreign)) {
            foreach ($foreign as $columnName => $item) {
                $tableName = $prefix . $item['table'];
                $currentTable = $database->isTableExists($tableName)
                    ? $database->getTableDetails($tableName)->getName()
                    : (
                        $database->isTableExists($item['table'])
                        ? $database->getTableDetails($item['table'])->getName()
                        : $item['table']
                );
                /*
                if (!$database->isTableExists($item['table'])
                    && $item['table'] !== $table->getName()
                ) {
                    throw new RuntimeException(
                        sprintf(
                            $this->translate('Foreign table %s is not exists on for foreign %s'),
                            $item['table'],
                            $columnName
                        )
                    );
                }
                if (!$currentTable->hasColumn($item['column'])) {
                    throw new RuntimeException(
                        sprintf(
                            $this->translate(
                                'Foreign table column %s.%s is not exists on for foreign %s'
                            ),
                            $item['table'],
                            $item['column'],
                            $columnName
                        )
                    );
                }
                */

                $table->addForeignKeyConstraint(
                    $currentTable,
                    [$columnName],
                    [$item['column']],
                    $item['options'],
                    $item['name']
                );
            }
        }

        $collation = $this->getTableSchemaCollation();
        $collation = is_string($collation) ? trim(strtolower($collation)) : null;
        if ($collation && $isPostgreSQL) {
            $collation = str_starts_with($collation, 'utf8') ? 'UTF-8' : $collation;
        }

        if ($supportCollation
            && $collation
            && ($definitionCollate = $database->getCollationByName($collation))
        ) {
            $table->addOption('collation', $definitionCollate['collate']);
            $table->addOption('charset', $definitionCollate['charset']);
        }

        return $table;
    }
}