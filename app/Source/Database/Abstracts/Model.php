<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Database\Abstracts;

use BadMethodCallException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Database\Exceptions\EmptyDataException;
use TrayDigita\Streak\Source\Database\Instance;
use TrayDigita\Streak\Source\Database\ResultData;
use TrayDigita\Streak\Source\Database\ResultQuery;
use TrayDigita\Streak\Source\Database\Traits\ModelSchema;
use TrayDigita\Streak\Source\Helper\Generator\RandomString;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

abstract class Model extends AbstractContainerization
{
    /**
     * @var array
     */
    private static array $recordDatabaseTables = [];

    /**
     * @var array
     */
    private static array $recordDatabaseTableModel = [];

    use ModelSchema,
        TranslationMethods,
        EventsMethods;

    /**
     * @var Instance
     */
    public readonly Instance $instance;

    /**
     * @var QueryBuilder
     */
    public readonly QueryBuilder $queryBuilder;

    /**
     * @var bool
     */
    protected bool $modelAutoPrefix = true;

    /**
     * @var bool use prefix to force using prefix on model
     */
    protected bool $modelUsePrefix = false;

    /**
     * @var string
     */
    private string $modelUniqueId;

    /**
     * @var bool
     */
    private bool $modelCheckedTable = false;

    /**
     * @var array<string, string>
     */
    private array $modelPrimaryKeys = [];

    /**
     * @var array<string, string>
     */
    private array $modelAliasesColumns = [];

    /**
     * @var array<string, Column>
     */
    private array $modelColumns = [];

    /**
     * @var array<int, array<string, Column>>
     */
    private array $modelUniqueIndexes = [];

    /**
     * @var array
     */
    private array $modelRelations = [];

    /**
     * @var array<string, mixed>
     */
    private array $modelOldData = [];

    /**
     * @var array<string, mixed>
     */
    private array $modelData = [];

    /**
     * @var array<string, mixed>
     */
    private array $modelDataSet = [];

    /**
     * @var bool
     */
    private bool $modelUseForeign = true;

    /**
     * @var string
     */
    public readonly string $modelTableName;

    /**
     * @var bool
     */
    private bool $modelFetched = false;
    /**
     * @var bool
     */
    private bool $modelInFetchData = false;

    /**
     * @var string
     */
    protected string $tableName = '';

    /** @noinspection PhpUnhandledExceptionInspection */
    public function __construct(?Instance $instance = null)
    {
        $instance ??= Container::getInstance()->get(Instance::class);
        parent::__construct($instance->container);
        $this->instance = $instance;
        $this->queryBuilder = $instance->createQueryBuilder();
        $this->modelUniqueId = RandomString::createUniqueHash();

        $this->determineTableName();
        $this->modelTableName = $this->tableName;
        $this->queryBuilder->from($this->tableName);
    }

    /**
     * @throws Exception
     */
    final protected function determineTableName(): static
    {
        if ($this->modelCheckedTable) {
            return $this;
        }
        $this->modelCheckedTable = true;
        try {
            $databaseName = $this->instance->getDatabase();
        } catch (Throwable) {
            $databaseName = '';
        }
        if (!isset(self::$recordDatabaseTables[$databaseName])) {
            self::$recordDatabaseTables[$databaseName] = [];
            try {
                $tableNames = $this->instance->getTableNames();
                foreach ($tableNames as $name) {
                    self::$recordDatabaseTables[$databaseName][strtolower($name)] = $name;
                }
            } catch (Throwable) {
                // pass
            }
        }

        $prefix = strtolower($this->instance->prefix);
        $tables = self::$recordDatabaseTables[$databaseName];
        $table_name        = trim($this->tableName);
        $this->tableName   = $table_name;
        $originalClassName = get_class($this);
        if ($table_name !== '') {
            $table_lower = strtolower($table_name);
            if ($this->modelUsePrefix) {
                $this->tableName = $tables[$prefix.$table_lower]??$prefix.$this->tableName;
            } elseif ($this->modelAutoPrefix) {
                $this->tableName = $tables[$prefix.$table_lower]??($tables[$table_lower]??$this->tableName);
            } else {
                $this->tableName = $tables[$table_lower]??$this->tableName;
            }
        } else {
            if (!isset(self::$recordDatabaseTableModel[$originalClassName])) {
                $className        = Consolidation::getBaseClassName($originalClassName);
                $lower_class_name = strtolower($className);
                $table_lower_class = $prefix.$lower_class_name;

                if (isset($tables[$table_lower_class])) {
                    $lower_class_name = $table_lower_class;
                } else {
                    $lower_class = preg_replace('~([A-Z])~', '_$1', $className);
                    $lower_class = strtolower(trim($lower_class, '_'));
                    $lower_class = $prefix . $lower_class;
                    if (!isset($tables[$lower_class])) {
                        $lower_class = preg_replace('~[_]+~', '_', $lower_class_name);
                    }
                    if (isset($tables[$lower_class])) {
                        $lower_class_name = $lower_class;
                    }
                }

                if (!isset($tables[$lower_class_name])) {
                    $lower_class_name = preg_replace('~([A-Z])~', '_$1', $className);
                    $lower_class_name = strtolower(trim($lower_class_name, '_'));
                }
                if (!isset($tables[$lower_class_name])) {
                    $lower_class_name = preg_replace('~[_]+~', '_', $lower_class_name);
                }
                self::$recordDatabaseTableModel[$originalClassName] = $tables[$lower_class_name] ?? '';
            }
            $this->tableName = self::$recordDatabaseTableModel[$originalClassName];
        }

        if ($this->tableName === '') {
            throw new Exception(
                sprintf(
                    $this->translate('Table for model %s is not exist.'),
                    $originalClassName
                )
            );
        }
        if (!$this->instance->isTableExists($this->tableName)) {
            return $this;
        }

        $this->modelPrimaryKeys = [];
        $tables = $this->instance->getTableDetails($this->tableName);
        $this->modelColumns = [];
        foreach ($tables->getColumns() as $column) {
            $columnName = $column->getName();
            $lowerColumn = strtolower($columnName);
            $this->modelColumns[$lowerColumn] = $column;
            $this->modelAliasesColumns[str_replace('_', '', $lowerColumn)] = $columnName;
        }

        $primaryKeys = $tables->getPrimaryKey();
        if ($primaryKeys !== null) {
            foreach ($primaryKeys->getColumns() as $key) {
                $this->modelPrimaryKeys[strtolower($key)] = $key;
            }
        }

        $this->modelUniqueIndexes = [];
        foreach ($tables->getIndexes() as $item) {
            if (!$item->isUnique()) {
                continue;
            }
            $data = [];
            foreach ($item->getColumns() as $column) {
                $data[strtolower($column)] = $column;
            }
            $this->modelUniqueIndexes[] = $data;
        }
        $this->modelRelations = [];
        foreach ($tables->getForeignKeys() as $name => $foreignKey) {
            $this->modelRelations[$name] = [
                'name'   => $name,
                'source' => $foreignKey->getLocalColumns()[0]??null,
                'table'  => $foreignKey->getForeignTableName(),
                'target' => $foreignKey->getForeignColumns()[0]??null
            ];
        }

        return $this;
    }

    public function __clone(): void
    {
        $this->queryBuilder->resetQueryParts();
        $this->modelUniqueId = RandomString::createUniqueHash();
    }

    /**
     * @param bool $modelUseForeign
     */
    public function setModelUseForeign(bool $modelUseForeign): void
    {
        $this->modelUseForeign = $modelUseForeign;
    }

    /**
     * @return bool
     */
    public function isModelUseForeign(): bool
    {
        return $this->modelUseForeign;
    }

    /**
     * @return bool
     */
    public function isModelFetched(): bool
    {
        return $this->modelFetched;
    }

    /**
     * @return string
     */
    public function getModelUniqueId(): string
    {
        return $this->modelUniqueId;
    }

    /**
     * @return Instance
     */
    public function getInstance(): Instance
    {
        return $this->instance;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * @return bool
     */
    public function isModelAutoPrefix(): bool
    {
        return $this->modelAutoPrefix;
    }

    /**
     * @return bool
     */
    public function isModelUsePrefix(): bool
    {
        return $this->modelUsePrefix;
    }

    /**
     * @return bool
     */
    public function isModelCheckedTable(): bool
    {
        return $this->modelCheckedTable;
    }

    /**
     * @return array
     */
    public function getModelAliasesColumns(): array
    {
        return $this->modelAliasesColumns;
    }

    /**
     * @return array
     */
    public function getModelPrimaryKeys(): array
    {
        return $this->modelPrimaryKeys;
    }

    /**
     * @return array
     */
    public function getModelColumns(): array
    {
        return $this->modelColumns;
    }

    /**
     * @param string $name
     *
     * @return ?Column
     */
    #[Pure] public function getColumn(string $name) : ?Column
    {
        return $this->modelColumns[$this->guessColumnName($name)]??null;
    }

    /**
     * @return array
     */
    public function getModelUniqueIndexes(): array
    {
        return $this->modelUniqueIndexes;
    }

    public function getModelRelations(): array
    {
        return $this->modelRelations;
    }

    public function getModelOldData(): array
    {
        return $this->modelOldData;
    }

    public function getModelData(): array
    {
        return $this->modelData;
    }

    public function getModelDataSet(): array
    {
        return $this->modelDataSet;
    }

    /**
     * @return string
     */
    public function getModelTableName(): string
    {
        return $this->modelTableName;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function set(string $key, $value) : static
    {
        $this->modelDataSet[$this->guessColumnName($key)] = $value;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @param mixed $found
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null, mixed &$found = null) : mixed
    {
        $name = $this->guessColumnName($key);
        $found = array_key_exists($name, $this->modelData);
        return $found ? $this->modelData[$name] : $default;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    #[Pure] public function guessColumnName(string $name) : string
    {
        $lower = strtolower($name);
        if (isset($this->modelAliasesColumns[$lower])) {
            return $this->modelAliasesColumns[$lower];
        }
        if (isset($this->modelColumns[$lower])) {
            return $this->modelColumns[$lower]->getName();
        }

        return $name;
    }

    public function __toString(): string
    {
        return serialize($this->modelData);
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __set(string $name, $value)
    {
        $this->set($name, $value);
    }

    #[Pure] public function __serialize(): array
    {
        return $this->modelData;
    }

    public function __unserialize(array $data): void
    {
        $this->modelData = $data;
    }

    #[Pure] public function jsonSerialize() : array
    {
        return $this->modelData;
    }

    public function __call(string $name, array $arguments)
    {
        $newName = substr($name, 2);
        $lowerName = strtolower(substr($newName, 0, 3));
        return match ($lowerName) {
            'set' => $this->set($newName, ...$arguments),
            'get' => $this->get($newName),
            default => throw new BadMethodCallException(
                sprintf(
                    $this->translate("Call to undefined Method %s."),
                    $name
                ),
                E_USER_ERROR
            ),
        };
    }

    /**
     * @param string $name
     * @param $value
     *
     * @return mixed
     * @throws ConversionException
     * @throws Exception
     */
    public function convertToPhpValue(string $name, $value): mixed
    {
        $columnType = $this->getColumn($name)?->getType();
        return $columnType ? $columnType->convertToPHPValue(
            $value,
            $this->instance->getDatabasePlatform()
        ) : $value;
    }

    /**
     * @param string $name
     * @param $value
     *
     * @return mixed
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function convertForDatabaseValue(string $name, $value): mixed
    {
        return $this->filterDatabaseValue($name, $value);
    }

    /**
     * @param string $name
     * @param $value
     *
     * @return mixed
     * @throws ConversionException
     * @throws Exception
     */
    public function convertToDatabaseValue(string $name, $value): mixed
    {
        $columnType = $this->getColumn($name)?->getType();
        return $columnType ? $columnType->convertToDatabaseValue(
            $value,
            $this->instance->getDatabasePlatform()
        ) : $value;
    }

    /**
     * @param string $columnName
     * @param $value
     *
     * @return ?Model
     */
    public function getForeign(string $columnName, $value) : ?Model
    {
        return null;
    }

    protected function filterResultValue(string $name, $value): mixed
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $value = $this->filterValue($name, $value);
        if ($this->modelUseForeign && $this->getForeignData($name)) {
            $temp = $this->getForeign($name, $value);
            if ($temp instanceof Model) {
                $value = $temp;
            }
            unset($temp);
        }

        return $value;
    }

    /**
     * @param string $columnName
     *
     * @return false|array
     */
    public function getForeignData(string $columnName) : false|array
    {
        foreach ($this->modelRelations as $items) {
            if ($columnName === $items['source']) {
                return $items;
            }
        }
        return false;
    }

    /**
     * Filter the value to set as php
     */
    public function filterValue(string $name, $value) : mixed
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $conversion = $this->convertToPhpValue($name, $value);
        $type = $this->getColumn($name)?->getType();
        if ($type && ($type = get_class($type))) {
            switch ($type) {
                case BigIntType::class:
                case IntegerType::class:
                    return (int) $conversion;
                case FloatType::class:
                    return (float) $conversion;
                case BooleanType::class:
                    return (bool) $conversion;
            }
        }
        return $conversion;
    }

    protected function filterDatabaseValue(string $name, $value)
    {
        $skip = false;
        if ($value instanceof Model && ($data = $this->getForeignData($name))) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $value = $value->convertToDatabaseValue(
                $data['target'],
                $value->get($data['target'], $value)
            );
            $skip = true;
        }
        if ($skip) {
            return $value;
        }
        $value = is_string($value) || is_null($value) || is_bool($value)
            ? $value
            : $this->tryToSerial($name, $value);
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->convertToDatabaseValue($name, $value);
    }

    private function tryToSerial(string $name, $value) : mixed
    {
        return $this->getColumn($name) ? $value : Validator::maybeShouldSerialize($value);
    }

    final public function setInternalData(array $data): static
    {
        $last = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class']??null;
        if ($last !== ResultData::class && $last !== __CLASS__) {
            throw new RuntimeException(
                sprintf(
                    $this->translate('%s can not called outside %s'),
                    sprintf(
                        '%s::%s',
                        get_class($this),
                        __METHOD__
                    ),
                    ResultData::class
                )
            );
        }

        $this->modelFetched     = true;
        $this->modelInFetchData = true;
        $this->modelDataSet     = [];
        $this->modelOldData = $this->modelData;
        $this->modelData = [];
        foreach ($data as $key => $value) {
            $value = Validator::unSerialize($value);
            $this->modelData[$key] = $this->filterResultValue($key, $value);
        }
        $this->modelInFetchData = false;
        return $this;
    }

    /**
     * @param ResultQuery $resultQuery
     * @param ?array $propertyToRead
     * @param $found
     *
     * @return ResultQuery
     */
    private function createFetchResultQuery(
        ResultQuery $resultQuery,
        &$found = null,
        ?array $propertyToRead = null
    ) : ResultQuery {
        $found = true;
        $propertyToRead ??= [
            'modelDataSet',
            'modelData',
            'modelOldData',
        ];
        if (!empty($this->modelPrimaryKeys)) {
            foreach ($propertyToRead as $named) {
                if (empty($this->$named) || !is_array($this->$named)) {
                    continue;
                }
                foreach ($this->modelPrimaryKeys as $item) {
                    if (isset($this->$named[$item])) {
                        return $resultQuery->is($item, $this->$named[$item]);
                    }
                }
            }
        }

        if (!empty($this->modelUniqueIndexes)) {
            foreach ($propertyToRead as $named) {
                if (empty($this->$named) || !is_array($this->$named)) {
                    continue;
                }
                foreach ($this->modelUniqueIndexes as $indexes) {
                    $intersect = array_intersect($this->$named, $indexes);
                    if (count($indexes) === count($intersect)) {
                        foreach ($intersect as $key => $item) {
                            $resultQuery->is($key, $item);
                        }
                        return $resultQuery;
                    }
                }
            }
        }

        $found = false;
        foreach ($this->modelDataSet as $key => $value) {
            $columnName = $this->getColumn($key);
            if (!$columnName) {
                continue;
            }
            $columnName = $columnName->getName();
            is_array($value)
                ? $resultQuery->in($columnName, $value)
                : $resultQuery->is($columnName, $value);
        }

        return $resultQuery;
    }

    /**
     * @param $found
     *
     * @return ResultQuery
     */
    public function result(&$found = null) : ResultQuery
    {
        return $this->createFetchResultQuery(new ResultQuery($this), $found);
    }

    /**
     * @param string $name
     * @param $value
     * @param Instance|null $instance
     *
     * @return ResultQuery
     */
    public static function where(string $name, $value, Instance $instance = null): ResultQuery
    {
        return (new static($instance))->set($name, $value)->result();
    }

    /**
     * @param array|string|int|float|null $params
     * @param Instance|null $instance
     *
     * @return ResultQuery
     * @throws Exception
     */
    public static function find(array|string|int|float|null $params = null, ?Instance $instance = null) : ResultQuery
    {
        $model = new static($instance);
        if (func_num_args() === 0) {
            return $model->result();
        }

        if (is_array($params)) {
            foreach ($params as $key => $item) {
                if (!is_string($key)) {
                    throw new Exception(
                        sprintf(
                            $model->translate(
                                'Argument parameters key index must be as a string, %s given.'
                            ),
                            gettype($key)
                        )
                    );
                }
                $model->set($key, $item);
            }
        } else {
            $keys = $model->getModelPrimaryKeys();
            $key = reset($keys);
            if ($key === false) {
                throw new Exception(
                    sprintf(
                        $model->translate('Table %s does not have primary key'),
                        $model->getTableName()
                    )
                );
            }
            $model->set($key, $params);
        }

        return $model->result();
    }

    /***
     * @param array|string|int|float|null $params
     * @param Instance|null $instance
     *
     * @return ?static
     * @throws Exception
     */
    public static function first(array|string|int|float $params = null, ?Instance $instance = null): ?static
    {
        return static::find(...func_get_args())->fetchFirst();
    }

    /**
     * @param array|string|int|float|null $params
     * @param Instance|null $instance
     *
     * @return ?static
     * @throws Exception
     */
    public static function last(array|string|int|float|null $params = null, ?Instance $instance = null): ?static
    {
        return static::find(...func_get_args())->fetchLast();
    }

    /**
     * @param int $offset
     * @param array|string|int|float|null $params
     * @param Instance|null $instance
     *
     * @return ?static
     * @throws Exception
     */
    public static function take(int $offset, array|string|int|float|null $params = null, ?Instance $instance = null): ?static
    {
        $args = func_get_args();
        array_shift($args);
        return static::find(...$args)->fetchTake($offset);
    }

    /**
     * @param ?array $params
     *
     * @return int
     * @throws Exception
     */
    public function insert(?array $params = null) : int
    {
        foreach ((array) $params as $key => $value) {
            $this->set($key, $value);
        }
        if (empty($this->modelDataSet)) {
            throw new EmptyDataException(
                $this->translate('No data to be inserted.')
            );
        }

        $qb = $this->queryBuilder;
        $columns = $this->getModelColumns();
        foreach ($this->getModelDataSet() as $key => $item) {
            if (!$this->getColumn($key)) {
                continue;
            }
            $hash = RandomString::createUniqueHash();
            $qb
                ->setValue($key, ":$hash")
                ->setParameter($hash, $this->filterDatabaseValue($key, $item));
            $contain = true;
            unset($columns[$key]);
        }

        if (empty($contain)) {
            return 0;
        }

        $mustBeSet = [];
        foreach ($columns as $key => $column) {
            if ($column->getAutoincrement()
                || $column->getDefault() !== null
            ) {
                continue;
            }
            if (!$column->getNotnull()) {
                $hash = RandomString::createUniqueHash();
                $qb
                    ->setValue($key, ":$hash")
                    ->setParameter($hash, $this->filterDatabaseValue($key, null));
                continue;
            }
            $mustBeSet[] = $key;
        }

        if (!empty($mustBeSet)) {
            throw new Exception(
                sprintf(
                    $this->translate('Column (%s) must be set.'),
                    implode(', ', $mustBeSet)
                )
            );
        }

        return $qb->insert($this->tableName)->executeQuery()->rowCount();
    }

    /**
     * @param ?array $params
     *
     * @return int
     * @throws Exception
     */
    public function update(?array $params = null): int
    {
        foreach ((array) $params as $key => $value) {
            $this->set($key, $value);
        }

        if (empty($this->modelDataSet)) {
            return 0;
        }

        $qb = $this->createFetchResultQuery(new ResultQuery($this), $found, [
            'modelData',
            'modelOldData',
        ])->model->queryBuilder;
        $contain = false;
        foreach ($this->modelDataSet as $key => $item) {
            if (!$this->getColumn($key)) {
                continue;
            }

            $contain = true;
            $hash = RandomString::createUniqueHash();
            $qb
                ->set($key, ":$hash")
                ->setParameter($hash, $this->filterDatabaseValue($key, $item));
        }

        if (!$contain) {
            return 0;
        }

        $update = $qb->update($this->tableName)->executeQuery()->rowCount();
        $this->doAfterUpdate(
            $this->modelDataSet,
            $this->modelData
        );

        return $update;
    }

    protected function doAfterUpdate(array $dataSet, ?array $data = null)
    {
        // blank
    }

    /**
     * @param ?array $params
     *
     * @return int
     * @throws Exception
     */
    public function save(?array $params = null): int
    {
        if (empty($params) && empty($this->modelDataSet)) {
            return 0;
        }

        foreach ((array) $params as $key => $value) {
            $this->set($key, $value);
        }

        $result = $this->createFetchResultQuery(new ResultQuery($this), $found, [
            'modelData',
            'modelOldData',
        ]);

        return $result->getResultData()->fetchFirst()
            ? $this->update($params)
            : $this->insert($params);
    }
}
