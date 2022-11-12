<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Database;

use BadMethodCallException;
use Closure;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception as DoctrineException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use PDO;
use Psr\Cache\CacheItemPoolInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Database\Abstracts\Model;
use TrayDigita\Streak\Source\Models\ActionSchedulers;
use TrayDigita\Streak\Source\Models\ActionSchedulersLog;
use TrayDigita\Streak\Source\Models\Factory\UserMeta;
use TrayDigita\Streak\Source\Models\Factory\Users;
use TrayDigita\Streak\Source\Records\Collections;
use TrayDigita\Streak\Source\Configurations;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\StoragePath;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

/**
 * @mixin Connection
 */
class Instance extends AbstractContainerization
{
    use EventsMethods,
        TranslationMethods;

    /**
     * @var array
     */
    protected array $params = [];

    /**
     * @var ?Connection
     */
    protected ?Connection $connection = null;

    /**
     * @var string
     */
    protected string $quoteIdentifier = '`';

    /**
     * @var array<class-string<Model>>
     */
    protected array $registeredModels = [];

    /**
     * @var ?Configuration
     */
    protected ?Configuration $configuration = null;

    /**
     * @var string
     */
    public readonly string $prefix;

    /**
     * @var array
     */
    private static array $collations = [];

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        // config
        $collections = $container->get(Configurations::class)->get('database');
        $collections = $collections instanceof Collections
            ? $collections
            : new Collections();
        $args = [
            'user' => $collections['user'],
            'password' => $collections['password'],
            'dbname' => $collections['dbname'],
            'port' => $collections['port'],
            'prefix' => ''
        ];

        /* ------------------------------------
         * Normalize
         * ---------------------------------- */
        if (empty($args['user']) && isset($collections['username'])) {
            $args['user'] = $collections->get('username');
        }
        if (empty($args['dbname']) && isset($collections['name'])) {
            $args['dbname'] = $collections->get('name');
        }
        if ($args['password'] === null && isset($collections['pass'])) {
            $args['password'] = $collections->get('pass');
        }
        if ($args['port'] === null && isset($collections['dbport'])) {
            $args['port'] = $collections['dbport'];
        }

        if (isset($collections['charset'])) {
            $args['charset'] = $collections['charset'];
        } elseif (isset($collections['dbcharset'])) {
            $args['charset'] = $collections['dbcharset'];
        }

        if (isset($collections['host'])) {
            $args['host'] = $collections['host'];
        } elseif (isset($collections['dbhost'])) {
            $args['host'] = $collections['dbhost'];
        }

        if (isset($collections['unix_socket'])) {
            $args['unix_socket'] = $collections['unix_socket'];
        } elseif (isset($collections['socket'])) {
            $args['unix_socket'] = $collections['socket'];
        }

        if (isset($collections['path'])) {
            $args['path'] = $collections['path'];
        } elseif (isset($collections['dbpath'])) {
            $args['path'] = $collections['dbpath'];
        }
        if (isset($collections['prefix'])) {
            $args['prefix'] = $collections['prefix'];
        } elseif (isset($collections['dbprefix'])) {
            $args['prefix'] = $collections['prefix'];
        }
        if (!is_string($args['prefix'])) {
            $args['prefix'] = '';
        }
        $args['prefix'] = trim($args['prefix']);
        $this->prefix = $args['prefix'];

        if (isset($collections['driverOptions'])) {
            $args['driverOptions'] = $collections['driverOptions'];
        } elseif (isset($collections['options']) && is_array($collections['options'])) {
            $args['driverOptions'] = $collections['options'];
        }

        // normalize
        if (!is_array($args['driverOptions']??null)) {
            $args['driverOptions'] = [];
        }
        if (!is_numeric($args['port'])) {
            unset($args['port']);
        }

        $driver = $collections->get('driver');
        $driver = is_string($driver) ? strtolower(trim($driver)) : null;
        $driver = $driver?:null;
        if (!$driver || !is_string($driver)) {
            $driver = 'pdo_mysql';
        }

        $driver = $this->normalizeDatabaseDriverFromString($driver)?:'pdo_mysql';

        $args['driver'] = $driver;
        $args['driverOptions'] = $args['driverOptions']??[];
        $args['driverOptions'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        // set emulation
        $args['driverOptions'][PDO::ATTR_EMULATE_PREPARES] = true;

        $this->configuration = $this->createInternalConfiguration($collections);
        $this->params = $args;
    }

    /**
     * @param string $collation
     *
     * @return ?array
     */
    #[ArrayShape([
        'name' => 'string',
        'charset' => 'string',
        'collate' => 'string'
    ])] public function getCollationByName(string $collation) : ?array
    {
        return $this->getAvailableCollations()[strtolower($collation)]??null;
    }

    public function getDatabasePlatform() : AbstractPlatform
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->getConnection()->getDatabasePlatform();
    }

    /**
     * @return array
     */
    public function getAvailableCollations() : array
    {
        $name = null;
        try {
            $platform = $this->getDatabasePlatform();
            if ($platform instanceof AbstractMySQLPlatform) {
                $name = 'mysql';
            } elseif ($platform instanceof PostgreSQLPlatform) {
                $name = 'postgresql';
            }
            // elseif ($platform instanceof OraclePlatform) {
            //    $name = 'oracle';
            // }
            if (isset(self::$collations[$name])) {
                return self::$collations[$name];
            }

            self::$collations[$name] = [];
            switch ($name) {
                case 'postgresql':
                    /** @noinspection SqlResolve */
                    $result = $this->executeQuery(
                        'SELECT
                            collcollate as collate_name,
                            collname as name,
                            collctype as charset
                        FROM pg_collation'
                    );
                    self::$collations[$name]['utf-8'] = [
                        'name' => 'UTF-8',
                        'charset' => 'utf-8',
                        'collate' => 'en_US.UTF-8',
                    ];
                    while ($row = $result->fetchAssociative()) {
                        $collation = strtolower($row['collate_name']);
                        if (!$collation) {
                            // is default
                            continue;
                        }
                        $charset = str_contains($collation, '.')
                            ? substr(strrchr($collation, '.'), 1)
                            : 'utf-8'; // default charset
                        self::$collations[$name][$collation] = [
                            'name' => $row['name'],
                            'charset' => $charset,
                            'collate' => $collation,
                        ];
                    }
                    break;
                case 'mysql':
                    $result = $this->executeQuery('SHOW COLLATION');
                    while ($row = $result->fetchAssociative()) {
                        $collation = $row['Collation'] ??($row['collation'] ?? null);
                        $charset = $row['Charset'] ?? ($row['charset'] ?? null);
                        if (!$collation) {
                            continue;
                        }
                        self::$collations[$name][strtolower($collation)] = [
                            'name' => $collation,
                            'charset' => $charset,
                            'collate' => $collation,
                        ];
                    }
                    break;
                // case 'oci':
                    /** @noinspection SqlResolve */
                    /*$result = $this->executeQuery(
                        "SELECT
                            parameter as parameter,
                            value as value
                        FROM
                             nls_database_parameters
                        where
                              parameter
                                  in ('NLS_COMP', 'NLS_CHARACTERSET')"
                    );*/
                // break;
            }

            return self::$collations[$name];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @param string $driverName
     *
     * @return bool|string
     */
    public function normalizeDatabaseDriverFromString(string $driverName): bool|string
    {
        static $hasPdo = null;
        if ($hasPdo === null) {
            $hasPdo = class_exists('PDO');
        }
        if (trim($driverName)) {
            $driverName = trim(strtolower($driverName));
            // maria-db is a mysql, there was much of unknown people use it
            if (preg_match('~maria|mysq~i', $driverName)) {
                $driverName = preg_match('~sqli~', $driverName)
                    ? 'mysqli'
                    : 'pdo_mysql';
            } elseif (preg_match('~post?g|pg?sql~i', $driverName)) {
                $driverName = 'pdo_pgsql';
            } elseif (str_contains($driverName, 'sqlit')) {
                $driverName = 'pdo_sqlite';
            } elseif (str_contains($driverName, 'oci')) {
                $driverName = $hasPdo || preg_match('~pdo~', $driverName)
                    ? 'pdo_oci'
                    : 'oci8';
            } elseif (preg_match('~ibm|db2~i', $driverName)) {
                $driverName = 'ibm_db2';
            } elseif (preg_match('~mssql|sqlsrv~i', $driverName)) {
                $driverName = $hasPdo || preg_match('~pdo~', $driverName)
                    ? 'pdo_sqlsrv'
                    : 'sqlsrv';
            }

            if (in_array($driverName, DriverManager::getAvailableDrivers())) {
                return $driverName;
            }
        }

        return false;
    }

    /**
     * @param Collections $collections
     *
     * @return Configuration
     */
    private function createInternalConfiguration(Collections $collections) : Configuration
    {
        $config = $collections['configuration']??(
            $collections['configuration'] instanceof Collections
                ? $collections['configuration']->toArray()
                : (is_array($collections['configuration']) ? $collections['configuration'] : [])
            );
        $resultCache = null;
        $middlewares = null;
        // build configuration
        if (!$config instanceof Configuration) {
            $config= $config instanceof Collections ? $config->toArray() : $config;
            $config = !is_array($config) ? [$config] : $config;
            $configuration = new Configuration();
            $config = array_change_key_case($config, CASE_LOWER);
            $autoCommit = $config['autocommit']??null;
            is_bool($autoCommit) && $configuration->setAutoCommit($autoCommit);
            $middlewares = $config['middlewares']??null;
            if (is_array($middlewares)) {
                foreach ($middlewares as $key => $middleware) {
                    if (!$middleware instanceof Middleware) {
                        unset($middleware[$key]);
                    }
                }
                $middlewares = array_values($middlewares);
            }

            $resultCache = $config['resultcache']??null;
            $config = $configuration;
        }
        $middlewares = is_array($middlewares) ? $middlewares : [];
        $middlewares = $this->eventDispatch('Database:middlewares', $middlewares);
        if (is_array($middlewares)) {
            foreach ($middlewares as $key => $middleware) {
                if (!$middleware instanceof Middleware) {
                    unset($middleware[$key]);
                }
            }
            $middlewares = array_values($middlewares);
        }

        if (!empty($middlewares) && is_array($middlewares)) {
            $config->setMiddlewares($middlewares);
        }

        if (!$config->getResultCache() || !$resultCache instanceof CacheItemPoolInterface) {
            $cache = $this->getContainer(Configurations::class)->get('cache', new Configurations());
            $cache = !$cache instanceof Collections ? new Configurations() : $cache;
            $lifetime = $cache->get('lifetime', 0);
            $defaultLifetime = !is_int($lifetime) ? 0 : $lifetime;
            $lifetime = $this->eventDispatch('Cache:lifetime', $lifetime);
            $lifetime = !is_int($lifetime) ? $defaultLifetime : $lifetime;
            $adapter = $defaultLifetime === 0 ? ArrayAdapter::class : FilesystemAdapter::class;

            /**
             * Marshaller
             */
            $marshaller = $cache->get('marshaller');
            $marshaller = is_string($marshaller) && !is_a($marshaller, MarshallerInterface::class, true)
                ? $this->getContainer(MarshallerInterface::class)
                : ($marshaller instanceof MarshallerInterface
                    ? $marshaller
                    : (
                    is_string($marshaller)
                        ? new $marshaller
                        : $this->getContainer(MarshallerInterface::class))
                );
            $maxItems = (int) $this->eventDispatch('Cache:maxItems', 100);
            $maxItems = $maxItems < 10 ? 10 : (
            $maxItems > 1000 ? 1000 : $maxItems
            );
            $storeSerialize = (bool) $this->eventDispatch('Cache:storeSerialize', true);
            $maxLifetime = (float) $this->eventDispatch('Cache:storeSerialize', 0.0);
            $defaultMarshaller = is_string($marshaller) ? new $marshaller() : $marshaller;
            $marshaller = $this->eventDispatch('Cache:marshaller', $marshaller);
            $marshaller = $marshaller instanceof MarshallerInterface ? $marshaller : $defaultMarshaller;
            $resultCache = $adapter === FilesystemAdapter::class
                ? new FilesystemAdapter(
                    'database',
                    $lifetime,
                    $this->getContainer(StoragePath::class)->getCacheDirectory(),
                    $marshaller
                ) : new ArrayAdapter(
                    $lifetime,
                    $storeSerialize,
                    $maxLifetime,
                    $maxItems
                );
        }
        $config->setResultCache($resultCache);
        return $config;
    }

    /**
     * @throws DoctrineException
     */
    public function getConnection() : Connection
    {
        if (!$this->connection) {
            $this->connection = DriverManager::getConnection(
                $this->params,
                $this->configuration
            );
            $this->eventDispatch('Database:connection', $this->connection, $this);
            $driver = $this->params['driver'];
            $query = null;
            // set utc timezone
            if (str_contains($driver, 'mysql')) {
                $query = "SET NAMES UTF8,";
                // $query .= "CHARACTER_SET_DATABASE = UTF8MB4,";
                // $query .= "CHARACTER_SET_SERVER = UTF8MB4,";
                // $query .= "CHARACTER_SET_RESULTS = UTF8MB4,";
                // $query .= "CHARACTER_SET_CONNECTION = UTF8MB4,";
                // $query .= "CHARACTER_SET_CLIENT = UTF8MB4,";
                $query .= "TIME_ZONE = '+00:00'";
                $query .= ";";
            } elseif (str_contains($driver, 'postgre')) {
                $query = "SET NAMES 'UTF8'; SET TIME ZONE '+00:00';";
            } elseif (str_contains($driver, 'oci')) {
                $query = "ALTER DATABASE SET TIME_ZONE='+00:00';";
            } elseif (str_contains($driver, 'ibm')) {
                $query = "SET SESSION TIME_ZONE='+00:00';";
            }
            $query && $this->connection->executeQuery($query);
        }
        return $this->connection;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getQuoteIdentifier(): string
    {
        return $this->quoteIdentifier;
    }

    /**
     * Trimming table for safe usage
     *
     * @param mixed $table
     * @return mixed
     */
    public function namedQuote(mixed $table): mixed
    {
        if (is_array($table)) {
            foreach ($table as $key => $value) {
                $table[$key] = $this->namedQuote($value);
            }
            return $table;
        } elseif (is_object($table)) {
            foreach (get_object_vars($table) as $key => $value) {
                $table->{$key} = $this->namedQuote($value);
            }
            return $table;
        }
        if (is_string($table)) {
            $tableArray = explode('.', $table);
            foreach ($tableArray as $key => $value) {
                $tableArray[$key] = trim(
                    trim(
                        trim($value),
                        $this->getQuoteIdentifier()
                    )
                );
            }
            $table = implode('.', $tableArray);
        }

        return $table;
    }

    /**
     * @param $quoteStr
     *
     * @return mixed
     * @uses Connection::quoteIdentifier()
     */
    public function quoteIdentifier($quoteStr): mixed
    {
        if ($quoteStr instanceof Closure || is_resource($quoteStr)) {
            throw new InvalidArgumentException(
                $this->translate(
                    "Invalid value to be quote, quote value could not be instance of `Closure` or as a `Resource`."
                ),
                E_USER_ERROR
            );
        }

        $quoteStr = $this->namedQuote($quoteStr);
        if (is_array($quoteStr)) {
            foreach ($quoteStr as $key => $value) {
                $quoteStr[$key] = $this->quoteIdentifier($value);
            }
            return $quoteStr;
        } elseif (is_object($quoteStr)) {
            foreach (get_object_vars($quoteStr) as $key => $value) {
                $quoteStr->{$key} = $this->quoteIdentifier($value);
            }
            return $quoteStr;
        }

        if (!is_string($quoteStr)) {
            throw new InvalidArgumentException(
                $this->translate(
                    "Invalid value to be quote, quote value must be as a string."
                ),
                E_USER_ERROR
            );
        }

        return $this->connection->quoteIdentifier($quoteStr);
    }

    /**
     * @param string $like
     *
     * @return string
     */
    public function escapeLike(string $like): string
    {
        $platform = $this->getDatabasePlatform();
        $characters = '%_';
        if ($platform instanceof SQLServerPlatform) {
            $characters .= '[]';
        }
        return $platform
            ->escapeStringForLike(
                $like,
                $characters
            );
    }

    /**
     * Alternative multi variable type quote string
     *      Nested quote-able
     *
     * @param mixed $quoteStr
     * @param int $type
     *
     * @return mixed
     */
    public function quote(mixed $quoteStr, int $type = PDO::PARAM_STR): mixed
    {
        if ($quoteStr instanceof Closure || is_resource($quoteStr)) {
            throw new InvalidArgumentException(
                $this->translate(
                    "Invalid value to be quote, quote value could not be instance of `Closure` or as a `Resource`."
                ),
                E_USER_ERROR
            );
        }

        if (is_array($quoteStr)) {
            foreach ($quoteStr as $key => $value) {
                $quoteStr[$key] = $this->quote($value, $type);
            }
            return $quoteStr;
        } elseif (is_object($quoteStr)) {
            foreach (get_object_vars($quoteStr) as $key => $value) {
                $quoteStr->{$key} = $this->quote($value, $type);
            }
            return $quoteStr;
        }

        return $this->connection->quote($quoteStr, $type);
    }

    /**
     * Compile Bindings
     *     Take From CI 3 Database Query Builder, default string Binding use Question mark `?`
     *
     * @param  string $sql   sql statement
     * @param ?array $binds array of bind data
     *
     * @return ?string returning null if fail
     */
    public function compileBindsQuestionMark(string $sql, ?array $binds = null): ?string
    {
        if (empty($binds) || !str_contains($sql, '?')) {
            return $sql;
        } elseif (! is_array($binds)) {
            $binds = [$binds];
            $bind_count = 1;
        } else {
            // Make sure we're using numeric keys
            $binds = array_values($binds);
            $bind_count = count($binds);
        }
        // Make sure not to replace a chunk inside a string that happens to match the bind marker
        if ($countMatches = preg_match_all("/'[^']*'/i", $sql, $matches)) {
            $countMatches = preg_match_all(
                '/\?/i', # regex
                str_replace(
                    $matches[0],
                    str_replace('?', str_repeat(' ', 1), $matches[0]),
                    $sql,
                    $countMatches
                ),
                $matches, # matches
                PREG_OFFSET_CAPTURE
            );
            // Bind values' count must match the count of markers in the query
            if ($bind_count !== $countMatches) {
                return null;
            }
        } elseif (($countMatches = preg_match_all('/\?/i', $sql, $matches, PREG_OFFSET_CAPTURE)) !== $bind_count) {
            return $sql;
        }

        do {
            $countMatches--;
            $escapedValue = is_int($binds[$countMatches])
                ? $binds[$countMatches]
                : $this->quote($binds[$countMatches]);
            if (is_array($escapedValue)) {
                $escapedValue = '('.implode(',', $escapedValue).')';
            }
            $sql = substr_replace($sql, $escapedValue, $matches[0][$countMatches][1], 1);
        } while ($countMatches !== 0);

        return $sql;
    }

    /**
     * Query using binding optionals statements
     *
     * @param string $sql
     * @param ?array $binds
     * @param mixed ...$arguments
     *
     * @return Result
     * @throws DoctrineException
     */
    public function executeDirect(string $sql, ?array $binds = null, ...$arguments) : Result
    {
        $sql = $this->compileBindsQuestionMark($sql, $binds);
        if ($sql === false) {
            throw new DoctrineException(
                sprintf(
                    $this->translate(
                        'Invalid statement binding count with sql query : %s.'
                    ),
                    $sql
                ),
                E_USER_WARNING
            );
        }

        array_unshift($arguments, $sql);
        return $this->connection->executeQuery(...$arguments);
    }

    /**
     * Prepare & Execute directly
     *
     * @param string $query
     * @param array $bind
     * @return Result
     * @throws DoctrineException
     */
    public function prepareDirect(string $query, array $bind = []) : Result
    {
        $stmt = $this->connection->prepare($query);
        return $stmt->executeQuery($bind);
    }

    /**
     * @param array $queries
     * @param array $params
     * @param null $exception
     *
     * @return bool
     */
    public function multiQuery(array $queries, array $params = [], &$exception = null): bool
    {
        if (empty($queries)) {
            return true;
        }
        try {
            foreach ($queries as $key => $query) {
                $this->connection->executeQuery($query, $params[$key] ?? []);
            }
            return true;
        } catch (Exception $e) {
            $exception = $e;
            return false;
        }
    }

    /**
     * --------------------------------------------------------------
     * SCHEMA
     *
     * Lists common additional Methods just for check & lists only
     * to use more - please @uses Instance::getSchemaManager()
     *
     * @see AbstractSchemaManager
     *
     * ------------------------------------------------------------ */

    /**
     * Get List Available Databases
     *
     * @return string[]
     * @throws DoctrineException
     */
    public function getDatabases() : array
    {
        return $this
            ->connection
            ->createSchemaManager()
            ->listDatabases();
    }

    /**
     * Get Doctrine Column of table
     *
     * @param string $tableName
     *
     * @return Column[]
     * @throws DoctrineException
     */
    public function getTableColumns(string $tableName) : array
    {
        if (trim($tableName) === '') {
            throw new InvalidArgumentException(
                $this->translate(
                    'Invalid parameter table name. Table name could not be empty.'
                ),
                E_USER_ERROR
            );
        }
        return $this
            ->connection
            ->createSchemaManager()
            ->listTableColumns($tableName);
    }

    /**
     * Lists the indexes for a given table returning an array of Index instances.
     *
     * Keys of the portable indexes list are all lower-cased.
     *
     * @param string $tableName The name of the table.
     *
     * @return Index[]
     * @throws DoctrineException
     */
    public function getTableIndexes(string $tableName) : array
    {
        if (trim($tableName) === '') {
            throw new InvalidArgumentException(
                $this->translate(
                    'Invalid parameter table name. Table name could not be empty.'
                ),
                E_USER_ERROR
            );
        }

        return $this
            ->connection
            ->createSchemaManager()
            ->listTableIndexes($tableName);
    }

    /**
     * @param array|string $tables
     *
     * @return bool
     * @throws DoctrineException
     */
    public function tablesExist(array|string $tables) : bool
    {
        return $this->isTableExists($tables);
    }

    /**
     * Check if table is Exists
     *
     * @param string|string[] $tables
     *
     * @return bool
     * @throws InvalidArgumentException*@throws DoctrineException
     * @throws DoctrineException
     */
    public function isTableExists(array|string $tables): bool
    {
        if (! is_string($tables) && !is_array($tables)) {
            throw new InvalidArgumentException(
                $this->translate(
                    'Invalid table name type. Table name must be as string or array.'
                ),
                E_USER_ERROR
            );
        }

        // $tables = $this->prefix($tables);
        ! is_array($tables) && $tables = [$tables];
        return $this
            ->connection
            ->createSchemaManager()
            ->tablesExist($tables);
    }

    /**
     * Returns a list of all tables in the current Database Connection.
     *
     * @return string[]
     * @throws DoctrineException
     */
    public function getTableNames() : array
    {
        return $this
            ->connection
            ->createSchemaManager()
            ->listTableNames();
    }

    /**
     * Get List Tables
     *
     * @return Table[]
     * @throws DoctrineException
     */
    public function getTables() : array
    {
        return $this
            ->connection
            ->createSchemaManager()
            ->listTables();
    }

    /**
     * Get Object Doctrine Table from Table Name
     *
     * @param string $tableName
     *
     * @return Table
     * @throws DoctrineException
     */
    public function getTableDetails(string $tableName) : Table
    {
        if (trim($tableName) === '') {
            throw new InvalidArgumentException(
                $this->translate(
                    'Invalid parameter table name. Table name could not be empty.'
                ),
                E_USER_ERROR
            );
        }

        return $this
            ->connection
            ->createSchemaManager()
            ->introspectTable($tableName);
    }

    /**
     * Lists the foreign keys for the given table.
     *
     * @param string $tableName The name of the table.
     *
     * @return ForeignKeyConstraint[]
     * @throws DoctrineException
     */
    public function getTableForeignKeys(string $tableName) : array
    {
        if (trim($tableName) === '') {
            throw new InvalidArgumentException(
                $this->translate(
                    'Invalid parameter table name. Table name could not be empty.'
                ),
                E_USER_ERROR
            );
        }
        return $this
            ->connection
            ->createSchemaManager()
            ->listTableForeignKeys($tableName);
    }

    /**
     * @param string $method
     * @param array $arguments
     *
     * @return mixed
     * @throws DoctrineException
     * @uses Connection
     * @uses getConnection()
     */
    public function __call(string $method, array $arguments)
    {
        /**
         * check if method exists on connection @see Connection !
         */
        if (method_exists($this->getConnection(), $method)) {
            return call_user_func_array([$this->getConnection(), $method], $arguments);
        }

        throw new BadMethodCallException(
            sprintf(
                $this->translate("Call to undefined Method %s."),
                $method
            ),
            E_USER_ERROR
        );
    }

    /**
     * @param Schema $fromSchema
     * @param ?Schema $withSchema
     *
     * @return SchemaDiff
     * @throws DoctrineException
     */
    public function compareSchema(Schema $fromSchema, ?Schema $withSchema = null) : SchemaDiff
    {
        $comparator = new Comparator();
        $withSchema = $withSchema ?? $this->createSchemaManager()->introspectSchema();
        return $comparator->compareSchemas($withSchema, $fromSchema);
    }

    /**
     * @param Table[] $tables
     * @param ?Schema $withSchema
     *
     * @return SchemaDiff
     * @throws DoctrineException
     * @throws SchemaException
     */
    public function compareSchemaFromTables(array $tables, ?Schema $withSchema = null) : SchemaDiff
    {
        return $this->compareSchema(
            new Schema($tables),
            $withSchema??$this->createSchemaManager()->introspectSchema()
        );
    }

    /**
     * @throws DoctrineException
     * @throws SchemaException
     */
    public function compareSchemaFromRegisteredModel(): SchemaDiff
    {
        $tables = [];
        foreach ($this->getRegisteredModelsName() as $model) {
            $model = $this->createModel($model);
            if (!$model) {
                continue;
            }
            $table                     = $model->getTableFromSchemaData();
            $tables[$table->getName()] = $table;
        }
        return $this->compareSchemaFromTables($tables);
    }

    public function registerModel(string|Model $model): bool
    {
        if ($this->hasModel($model)) {
            return true;
        }

        if (!is_string($model)) {
            $model = get_class($model);
            $lowerModel = strtolower($model);
            $this->registeredModels[$lowerModel] = $model;
            return true;
        }
        $lowerModel = strtolower(trim(trim($model), '\\'));
        if (isset($this->registeredModels[$lowerModel])) {
            return true;
        }
        if (!is_a($model, Model::class, true)) {
            return false;
        }
        try {
            $model = (new ReflectionClass($model))->getName();
            $lowerModel = strtolower($model);
            $this->registeredModels[$lowerModel] = $model;
            /**
             * (When) UserMeta or Users || ActionSchedulers or ActionSchedulersLog (registered)
             * It will also register to another, because relationship
             */
            switch ($model) {
                case UserMeta::class:
                    $this->registeredModels[strtolower(Users::class)] = Users::class;
                    break;
                case Users::class:
                    $this->registeredModels[strtolower(UserMeta::class)] = UserMeta::class;
                    break;
                case ActionSchedulers::class:
                    $this->registeredModels[strtolower(ActionSchedulersLog::class)] = ActionSchedulersLog::class;
                    break;
                case ActionSchedulersLog::class:
                    $this->registeredModels[strtolower(ActionSchedulers::class)] = ActionSchedulers::class;
                    break;
            }
        } catch (ReflectionException) {
            return false;
        }
        return true;
    }

    public function removeModel(string|Model $model)
    {
        if (!is_string($model)) {
            $lowerModel = strtolower(get_class($model));
        } else {
            $lowerModel = strtolower(trim(trim($model), '\\'));
        }
        unset($this->registeredModels[$lowerModel]);
    }

    /**
     * @param string $model
     *
     * @return ?Model
     */
    public function createModel(string $model) : ?Model
    {
        $lowerModel = strtolower(trim(trim($model), '\\'));
        if (!isset($this->registeredModels[$lowerModel])) {
            return null;
        }
        try {
            $ref = new ReflectionClass($model);
            if (!$ref->isSubclassOf(Model::class)) {
                throw new InvalidArgumentException(
                    sprintf(
                        $this->translate(
                            'Argument must be subclass of %s, % given.'
                        ),
                        Model::class,
                        $model
                    )
                );
            }
            $model = $ref->getName();
        } catch (ReflectionException) {
            throw new InvalidArgumentException(
                sprintf(
                    $this->translate(
                        'Class name %s is not valid.'
                    ),
                    $model
                )
            );
        }

        return new $model($this);
    }

    /**
     * @param string|Model $model
     *
     * @return bool
     */
    public function hasModel(string|Model $model) : bool
    {
        if (!is_string($model)) {
            $lowerModel = strtolower(get_class($model));
        } else {
            $lowerModel = strtolower(trim(trim($model), '\\'));
        }
        return isset($this->registeredModels[$lowerModel]);
    }

    /**
     * @return string[]
     */
    public function getRegisteredModelsName(): array
    {
        return array_values($this->registeredModels);
    }
}
