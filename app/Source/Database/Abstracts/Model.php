<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Database\Abstracts;

use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\BigIntType;
use Doctrine\DBAL\Types\BooleanType;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\IntegerType;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use RuntimeException;
use Serializable;
use Stringable;
use Throwable;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Database\Instance;
use TrayDigita\Streak\Source\Database\ResultCollection;
use TrayDigita\Streak\Source\Database\Traits\ModelSchema;
use TrayDigita\Streak\Source\Helper\Generator\RandomString;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Interfaces\ContainerizeInterface;
use TrayDigita\Streak\Source\Time;
use TrayDigita\Streak\Source\Traits\Containerize;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

abstract class Model implements ContainerizeInterface, Stringable, Serializable, JsonSerializable
{
    private static array $recordDatabaseTables = [];
    private static array $recordDatabaseTableModel = [];

    use TranslationMethods,
        Containerize,
        EventsMethods;
    use ModelSchema;

    /**
     * @var bool
     */
    protected bool $autoPrefix = true;

    /**
     * @var bool use prefix to force using prefix on model
     */
    protected bool $usePrefix = false;

    /**
     * @var Container
     */
    public readonly Container $container;

    /**
     * @var bool
     */
    private bool $checkedTable = false;

    /**
     * @var bool
     */
    protected bool $unserialize = true;

    /**
     * @var string
     */
    private string $uniqueId;
    /**
     * @var string
     */
    protected string $tableName = '';

    /**
     * @var array<string, string>
     */
    private array $primaryKeys = [];
    /**
     * @var array<string, Column>
     */
    private array $columns = [];
    /**
     * @var array<string, Column>
     */
    private array $uniqueIndexes = [];
    /**
     * @var array
     */
    protected array $relations = [];

    /**
     * @var array<string, mixed>
     */
    private ?array $data = null;

    /**
     * @var array<string, mixed>
     */
    private array $dataSet = [];

    /**
     * @var array<string, mixed>
     */
    private array $oldData = [];

    /**
     * @var ?int
     */
    private ?int $resultLimit = null;

    /**
     * @var ?int
     */
    private ?int $resultOffset = null;

    /**
     * @var array<array<string,string|null>>|null
     */
    private ?array $resultColumn = null;

    /**
     * @var QueryBuilder
     */
    public QueryBuilder $queryBuilder;

    /**
     * @var bool
     */
    private bool $useForeign = true;

    /**
     * @throws Exception
     */
    public function __construct(?Container $container = null)
    {
        $this->container = $container??Container::getInstance();
        $this->queryBuilder = $this->getContainer(Instance::class)->createQueryBuilder();
        $this->uniqueId = RandomString::createUniqueHash();
        // determine table
        $this->determineTableName();
        $this->queryBuilder->from($this->tableName);
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * @return array
     */
    public function getDataSet(): array
    {
        return $this->dataSet;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getOldData(): array
    {
        return $this->oldData;
    }

    /**
     * @return array<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param string $name
     *
     * @return Column|null
     */
    public function getColumn(string $name) : ?Column
    {
        return $this->columns[$name]??null;
    }

    /**
     * @return bool
     */
    public function isUseForeign(): bool
    {
        return $this->useForeign;
    }

    /**
     * @param bool $useForeign
     */
    public function setUseForeign(bool $useForeign): void
    {
        $this->useForeign = $useForeign;
    }

    /**
     * @throws Exception
     */
    protected function determineTableName(): static
    {
        if ($this->checkedTable) {
            return $this;
        }
        $database = $this->getDatabaseInstance();
        $this->checkedTable = true;
        try {
            $databaseName = $database->getDatabase();
        } catch (Throwable) {
            $databaseName = '';
        }
        if (!isset(self::$recordDatabaseTables[$databaseName])) {
            self::$recordDatabaseTables[$databaseName] = [];
            try {
                $tableNames = $this->getDatabaseInstance()->getTableNames();
                foreach ($tableNames as $name) {
                    self::$recordDatabaseTables[$databaseName][strtolower($name)] = $name;
                }
            } catch (Throwable) {
                // pass
            }
        }

        $prefix = strtolower($database->prefix);
        $tables = self::$recordDatabaseTables[$databaseName];
        $table_name        = trim($this->tableName);
        $this->tableName   = $table_name;
        $originalClassName = get_class($this);
        if ($table_name !== '') {
            $table_lower = strtolower($table_name);
            if ($this->usePrefix) {
                $this->tableName = $tables[$prefix.$table_lower]??$prefix.$this->tableName;
            } elseif ($this->autoPrefix) {
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

        $this->primaryKeys = [];
        $tables = $database->getTableDetails($this->tableName);
        $this->columns = [];
        foreach ($tables->getColumns() as $column) {
            $columnName = $column->getName();
            $this->columns[strtolower($columnName)] = $column;
        }

        $primaryKeys = $tables->getPrimaryKey();
        if ($primaryKeys !== null) {
            foreach ($primaryKeys->getColumns() as $key) {
                $this->primaryKeys[strtolower($key)] = $key;
            }
        }

        $this->uniqueIndexes = [];
        foreach ($tables->getIndexes() as $item) {
            if (!$item->isUnique()) {
                continue;
            }
            $data = [];
            foreach ($item->getColumns() as $column) {
                $data[strtolower($column)] = $column;
            }
            $this->uniqueIndexes[] = $data;
        }
        $this->relations = [];
        foreach ($tables->getForeignKeys() as $name => $foreignKey) {
            $this->relations[$name] = [
                'name'   => $name,
                'source' => $foreignKey->getLocalColumns()[0]??null,
                'table'  => $foreignKey->getForeignTableName(),
                'target' => $foreignKey->getForeignColumns()[0]??null
            ];
        }

        return $this;
    }

    /**
     * @param string $name
     * @param null $default
     *
     * @return mixed
     */
    public function getValueData(string $name, $default = null) : mixed
    {
        return is_array($this->data) & array_key_exists($name, $this->data)
            ? $this->data[$name]
            : $default;
    }

    /**
     * @return string
     */
    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * @return Instance
     */
    public function getDatabaseInstance(): Instance
    {
        return $this->getContainer(Instance::class);
    }

    /**
     * @return AbstractPlatform
     * @throws Exception
     */
    public function getDatabasePlatform() : AbstractPlatform
    {
        return $this->getDatabaseInstance()->getDatabasePlatform();
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function resetQuery() : static
    {
        $this->dataSet = [];
        $this->getQueryBuilder()->resetQueryParts();
        return $this;
    }

    public function set(string $key, $value) : static
    {
        $this->dataSet[$key] = $value;
        return $this;
    }

    /**
     * @param array $params
     *
     * @return static|false
     * @throws Exception
     */
    public function fetch(array $params = []): false|static
    {
        return $this->result($params)->fetch();
    }

    /**
     * @param array $params
     *
     * @return static[]
     * @throws Exception
     */
    public function all(array $params = []) : iterable
    {
        return $this->result($params)->fetchAll();
    }

    /**
     * @param array $params
     *
     * @return false|static
     * @throws Exception
     */
    public function first(array $params = []): false|static
    {
        return $this->result($params)->first();
    }

    /**
     * @param int $offset
     * @param array $params
     *
     * @return bool|static
     * @throws Exception
     */
    public function offset(int $offset = 0, array $params = []): bool|static
    {
        return $this->result($params)->offset($offset);
    }

    /**
     * @param array $params
     *
     * @return false|static
     * @throws Exception
     */
    public function last(array $params = []): bool|static
    {
        return $this->result($params)->last();
    }

    /**
     * @throws ConversionException
     * @throws Exception
     */
    final public function result(array $params = [], ?bool $useForeign = null) : ResultCollection
    {
        $setForeign = is_bool($useForeign);
        $obj = $setForeign ? clone $this : $this;
        $setForeign && $obj->setUseForeign($useForeign);
        return self::createFetchResultCollection($obj, $params);
    }

    /**
     * @param array $params
     * @param bool|null $useForeign
     * @param array|string|null $select
     *
     * @return Result
     * @throws ConversionException
     * @throws Exception
     */
    final public function useResult(
        array $params = [],
        ?bool $useForeign = null,
        array|string $select = null
    ) : Result {
        $setForeign = is_bool($useForeign);
        $obj = $setForeign || $select ? clone $this : $this;
        if ($select) {
            $obj->getQueryBuilder()->select($select);
        }
        $setForeign && $obj->setUseForeign($useForeign);
        return self::createFetchResult($obj, $params);
    }

    /**
     * @param array $params
     *
     * @return ResultCollection
     * @throws ConversionException
     * @throws Exception
     */
    final public function resultDirect(array $params = []) : ResultCollection
    {
        return $this->result($params, false);
    }

    /**
     * @param array $params
     *
     * @return ResultCollection
     * @throws ConversionException
     * @throws Exception
     */
    final public function resultForeign(array $params = []) : ResultCollection
    {
        return $this->result($params, true);
    }

    final public function __clone(): void
    {
        $this->queryBuilder = clone $this->getQueryBuilder();
    }

    /**
     * @throws ConversionException
     * @throws Exception
     * @internal
     */
    private function setInternalValue(string $name, $value): static
    {
        if (!is_array($this->data)) {
            $this->data = [];
        }
        $value = $this->isUnserialize() ? Validator::unSerialize($value) : $value;
        $this->data[$name] = $this->filterResultValue($name, $value);
        return $this;
    }

    /**
     * @return DateTimeImmutable
     */
    public function nowDateTime() : DateTimeImmutable
    {
        return $this->getContainer(Time::class)->newDateTimeUTC();
    }

    /**
     * @private
     */
    private function moveOldData()
    {
        $this->oldData = $this->data??[];
        $this->data = [];
    }

    final public function isFetched(): bool
    {
        return is_array($this->data);
    }

    /**
     * @return array
     */
    public function getPrimaryKeys(): array
    {
        return $this->primaryKeys;
    }
    /**
     * @param array|string|int|float $params
     * @param ?Container $container
     *
     * @return static
     * @throws Exception
     */
    public static function find(array|string|int|float $params, ?Container $container = null) : static
    {
        $model = new static($container);
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
            $keys = $model->getPrimaryKeys();
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

        return $model;
    }

    public function limitResult(?int $limit): static
    {
        $this->resultLimit = $limit;
        return $this;
    }

    public function offsetResult(?int $offset): static
    {
        $this->resultOffset = $offset;
        return $this;
    }

    public function orderByColumn(?string $column = null, string $sort = null) : static
    {
        $col = $column ? ($this->columns[strtolower($column)]?->getName()?:null) : null;
        $sort = !is_string($sort) || trim($sort) === '' ? null : trim(strtoupper($sort));
        if ($col) {
            $this->resultColumn   = [];
            $this->resultColumn[] = [$col, $sort];
        } else {
            $this->resultColumn = null;
        }
        return $this;
    }

    public function addOrderByColumn(?string $column = null, string $sort = null) : static
    {
        $col = $column ? ($this->columns[strtolower($column)]?->getName()?:null) : null;
        $sort = !is_string($sort) || trim($sort) === '' ? null : trim(strtoupper($sort));
        if ($col) {
            if ($this->resultColumn === null) {
                $this->resultColumn = [];
            }
            $this->resultColumn[] = [$col, $sort];
        }

        return $this;
    }

    /**
     * @throws Exception
     * @throws ConversionException
     */
    private static function loopDataSet(
        Model $model,
        QueryBuilder $qb,
        string $key,
        $value
    ) {
        $ex = $model->getDatabaseInstance()->createExpressionBuilder();
        $is_array = is_array($value);
        $hashes = [];
        $value = $is_array
            ? array_map(
            /**
             * @throws ConversionException
             * @throws Exception
             */
                fn ($e) => $model->filterDatabaseValue($key, $e),
                $value
            )
            : $model->filterDatabaseValue($key, $value);
        if ($is_array) {
            $hashes = [];
            $params = [];
            foreach ($value as $data) {
                $hash = RandomString::createUniqueHash();
                $params[$hash] = $data;
                $hashes[] = ":$hash";
            }
        } else {
            $hash = RandomString::createUniqueHash();
            $params = [$hash => $value];
        }

        $expression = $is_array ? $ex->in($key, $hashes) : $ex->eq($key, ":$hash");
        $qb->andWhere($expression);
        foreach ($params as $k => $param) {
            $qb->setParameter($k, $param);
        }
    }

    private static function whereDataSetUniqueIndexes(
        Model $model
    ) : array {
        $where = [];
        $uniqueIndexes = $model->uniqueIndexes;
        $data          = $model->getDataSet();
        foreach ($uniqueIndexes as $index) {
            $set = [];
            foreach ($index as $in) {
                if (isset($data[$in])) {
                    $set[$in] = $data[$in];
                }
            }
            if (count($set) === count($index)) {
                foreach ($set as $k => $v) {
                    $where[$k] = $v;
                }
                break;
            }
        }
        return $where;
    }

    /**
     * @param array $params
     *
     * @return QueryBuilder
     * @throws ConversionException
     * @throws Exception
     */
    private function createWhereUpdateDelete(array $params = []): QueryBuilder
    {
        $where = [];
        if (empty($where)) {
            foreach ($this->getData() as $key => $value) {
                if (isset($this->primaryKeys[$key])) {
                    $where[$key] = $value;
                }
            }
        }

        if (empty($where)) {
            foreach ($this->getOldData() as $key => $value) {
                if (isset($this->primaryKeys[$key])) {
                    $where[$key] = $value;
                }
            }
        }

        if (empty($where)) {
            foreach ($this->getDataSet() as $key => $value) {
                if (isset($this->primaryKeys[$key])) {
                    $where[$key] = $value;
                }
            }
        }

        empty($where) && $where = self::whereDataSetUniqueIndexes($this);

        if (empty($where)) {
            foreach ($params as $key => $value) {
                if (isset($this->primaryKeys[$key])) {
                    $where[$key] = $value;
                }
            }
        }

        $qb = clone $this->getQueryBuilder();
        $qb->resetQueryPart('where');
        if (empty($where)) {
            throw new RuntimeException(
                $this->translate('Update method does not where statement')
            );
        }
        foreach ($where as $key => $value) {
            self::loopDataSet($this, $qb, $key, $value);
        }

        return $qb;
    }

    /**
     * @param array $params
     *
     * @return int
     * @throws Exception
     */
    public function update(array $params = []): int
    {
        foreach ($params as $key => $value) {
            $this->set($key, $value);
        }
        $qb = $this->createWhereUpdateDelete($params);
        $contain = false;
        $dataSet = $this->getDataSet();
        foreach ($dataSet as $key => $item) {
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

        $update = $qb->update($this->getTableName())->executeQuery()->rowCount();
        if ($this->isFetched()) {
            $this->doAfterUpdate($dataSet);
        }
        unset($dataSet);

        return $update;
    }

    /**
     * @throws ConversionException
     * @throws Exception
     */
    protected function doAfterUpdate($dataSet)
    {
        $columns = $this->getColumns();
        $this->oldData = $this->getData();
        foreach ($dataSet as $key => $item) {
            if (!isset($columns[$key])) {
                continue;
            }
            $this->data[$key] = $this->convertToPhpValue($key, $item);
        }
    }

    /**
     * @param array $params
     *
     * @return int
     * @throws Exception
     */
    public function save(array $params = []): int
    {
        if (!empty($this->data)) {
            return $this->update($params);
        }

        foreach ($params as $key => $value) {
            $this->set($key, $value);
        }

        $primaries = [];
        foreach ($this->getDataSet() as $key => $val) {
            if (isset($this->primaryKeys[$key])) {
                $primaries[$key] = $val;
            }
        }

        if (!empty($primaries)) {
            $data = $this->find($primaries, $this->getContainer())->first();
            if ($data) {
                return $data->update($this->getDataSet());
            }
        } else {
            $where = self::whereDataSetUniqueIndexes($this);
            if (!empty($where) && ($data = $this->find($where, $this->getContainer())->first())) {
                return $data->update($this->getDataSet());
            }
        }

        return $this->insert($params);
    }

    /**
     * @param array $params
     *
     * @return int
     * @throws Exception
     */
    public function insert(array $params = []) : int
    {
        foreach ($params as $key => $value) {
            $this->set($key, $value);
        }

        $qb = $this->getQueryBuilder();
        $columns = $this->getColumns();
        foreach ($this->getDataSet() as $key => $item) {
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

        return $qb->insert($this->getTableName())->executeQuery()->rowCount();
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
            $this->getDatabasePlatform()
        ) : $value;
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
            $this->getDatabasePlatform()
        ) : $value;
    }

    /**
     * @param string $name
     * @param $value
     *
     * @return mixed
     * @throws ConversionException
     * @throws Exception
     */
    protected function filterResultValue(string $name, $value): mixed
    {
        $value = $this->filterValue($name, $value);
        if ($this->isUseForeign() && $this->getForeignData($name)) {
            $temp = $this->getForeign($name, $value);
            if ($temp instanceof Model) {
                $value = $temp;
            }
            unset($temp);
        }

        return $value;
    }

    /**
     * Filter the value to set as php
     *
     * @throws ConversionException
     * @throws Exception
     */
    public function filterValue(string $name, $value) : mixed
    {
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

    /**
     * @throws ConversionException
     * @throws Exception
     */
    protected function filterDatabaseValue(string $name, $value)
    {
        $skip = false;
        if ($value instanceof Model && ($data = $this->getForeignData($name))) {
            $value = $value->convertToDatabaseValue(
                $data['target'],
                $value->getValueData($data['target'], $value)
            );
            $skip = true;
        }
        if ($skip) {
            return $value;
        }
        $value = is_string($value) || is_null($value) || is_bool($value)
            ? $value
            : $this->tryToSerial($name, $value);
        return $this->convertToDatabaseValue($name, $value);
    }

    private function tryToSerial(string $name, $value) : mixed
    {
        return $this->getColumn($name) ? $value : Validator::maybeShouldSerialize($value);
    }

    /**
     * @throws Exception
     * @throws ConversionException
     */
    public function delete(array $params = []): int
    {
        foreach ($params as $key => $value) {
            $this->set($key, $value);
        }

        $qb = $this->createWhereUpdateDelete($params);
        return $qb->delete($this->getTableName())->executeQuery()->rowCount();
    }

    /**
     * @param Model $model
     * @param array $params
     *
     * @return ResultCollection
     * @throws Exception
     */
    private static function createFetchResultCollection(
        Model $model,
        array $params
    ) : ResultCollection {
        return new ResultCollection(
            self::createFetchResult($model, $params),
            $model,
            fn (Model $mod) => $mod->moveOldData(),
            fn (Model $mod, $key, $value) => $mod->setInternalValue($key, $value)
        );
    }

    /**
     * @throws ConversionException
     * @throws Exception
     */
    private static function createFetchResult(Model $model, array $params) : Result
    {
        foreach ($params as $key => $value) {
            $model->set($key, $value);
        }
        $model = clone $model;
        $qb = $model->getQueryBuilder();
        $selects = $qb->getQueryPart('select');
        empty($selects) && $qb->select(['*']);

        $qb->setMaxResults($model->resultLimit);
        $qb->setFirstResult($model->resultOffset ?? 0);

        if (!empty($model->resultColumn)) {
            foreach ($model->resultColumn as $col) {
                $qb->addOrderBy(reset($col), next($col)?:null);
            }
        }

        $dataset = $model->getDataSet();
        $primaryKeys = $model->getPrimaryKeys();
        $hasKey = false;
        foreach ($dataset as $key => $value) {
            if (!isset($primaryKeys[$key])) {
                continue;
            }
            $hasKey = true;
            self::loopDataSet($model, $qb, $key, $value);
        }

        if (!$hasKey) {
            foreach ($dataset as $key => $value) {
                self::loopDataSet($model, $qb, $key, $value);
            }
        }

        return $qb->executeQuery();
    }

    /**
     * @return bool
     */
    public function isUnserialize(): bool
    {
        return $this->unserialize;
    }

    public function __toString(): string
    {
        return serialize($this->getData());
    }

    public function serialize() : string|false
    {
        return serialize($this->getData());
    }

    public function unserialize($data)
    {
        $data = unserialize($data);
        $this->data = is_array($data) ? $data : $this->data;
    }

    #[Pure] public function __serialize(): array
    {
        return $this->getData();
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data;
    }

    #[Pure] public function jsonSerialize() : array
    {
        return $this->getData();
    }

    public function getForeignData(string $columnName) : false|array
    {
        foreach ($this->relations as $items) {
            if ($columnName === $items['source']) {
                return $items;
            }
        }
        return false;
    }

    public function getForeign(string $columnName, $value) : ?Model
    {
        return null;
    }

    public function __get(string $name)
    {
        return $this->getValueData($name);
    }
}
