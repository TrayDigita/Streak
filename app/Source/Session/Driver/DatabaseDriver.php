<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Session\Driver;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Throwable;
use TrayDigita\Streak\Source\Database\Instance;
use TrayDigita\Streak\Source\Session\Abstracts\AbstractSessionDriver;

class DatabaseDriver extends AbstractSessionDriver
{
    const DEFAULT_SESSION_TABLE = 'sessions';

    /**
     * @var ?string
     */
    protected ?string $sessionName = null;

    /**
     * @var array
     */
    protected array $closed = [];

    /**
     * @var bool
     */
    protected static bool $exists = false;

    /**
     * @var mixed|int|null
     */
    protected mixed $gc = null;

    protected string $sessionTable = self::DEFAULT_SESSION_TABLE;
    protected string $nowExpression = 'now()';

    /**
     * @throws Exception
     */
    protected function afterConstruct()
    {
        $this->gc = @ini_get('session.gc_maxlifetime');
        $this->gc = is_numeric($this->gc) ? (int) $this->gc : null;
        $this->sessionTable = $this->eventDispatch('Session:table', $this->sessionTable);
        if (!is_string($this->sessionTable)
            || !preg_match('~^[a-z0-9]{2}([a-z0-9_]+)?$~i', $this->sessionTable)
        ) {
            $this->sessionTable = self::DEFAULT_SESSION_TABLE;
        }
        $platform = $this->getContainer(Instance::class)->getDatabasePlatform();
        if ($platform instanceof OraclePlatform) {
            $this->nowExpression = 'TO_CHAR(CURRENT_TIMESTAMP, \'YYYY-MM-DD HH24:MI:SS\')';
        }
    }

    /**
     * @return string
     */
    public function getSessionTable(): string
    {
        return $this->sessionTable;
    }

    public function close(): bool
    {
        if (!$this->sessionName) {
            return false;
        }

        $this->sessionName = null;
        return true;
    }

    public function destroy($id): bool
    {
        if (!$this->sessionName || !is_string($this->sessionName)) {
            return false;
        }

        $database = $this->getContainer(Instance::class);
        $exp = $database->createExpressionBuilder();
        try {
            $database
                ->createQueryBuilder()
                ->delete($this->getSessionTable())
                ->where($exp->eq('session_name', ':session_name'))
                ->andWhere($exp->eq('session_id', ':session_id'))
                ->setParameters([
                    'session_name' => $this->sessionName,
                    'session_id' => $id
                ])->executeQuery();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime) : int|false
    {
        $database = $this->getContainer(Instance::class);
        $exp = $database->createExpressionBuilder();
        $qb = $database
            ->createQueryBuilder()
            ->delete($this->getSessionTable())
            ->where($exp->neq('updated_at', 0))
            ->andWhere(
                $exp->or(
                    $exp->lt(
                        'updated_at +  INTERVAL max_lifetime SECOND ',
                        $this->nowExpression
                    ),
                    $exp->and(
                        $exp->gt(
                            $exp->lt("updated_at +  INTERVAL $max_lifetime SECOND", $this->nowExpression),
                            $exp->lt("updated_at +  INTERVAL max_lifetime SECOND", $this->nowExpression)
                        )
                    )
                )
            );
        try {
            $result = $qb->executeQuery();
            return $result->rowCount()?:1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws SchemaException
     * @throws Exception
     */
    public function open(string $path, string $name) : bool
    {
        $this->sessionName = $name;
        // call GC
        if (is_int($this->gc)) {
            $this->gc($this->gc);
            $this->gc = null;
        }
        if (self::$exists) {
            return true;
        }

        $database = $this->getContainer(Instance::class);
        if (!$database->isTableExists($this->getSessionTable())) {
            $table   = new Table($this->getSessionTable());
            $columns = [
                'session_id' => [
                    'session_id',
                    Types::STRING,
                    [
                        'notNull' => true,
                        'length' => 255,
                    ]
                ],
                'session_name' => [
                    'session_name',
                    Types::STRING,
                    [
                        'notNull' => true,
                        'length' => 255,
                    ]
                ],
                'session_data' => [
                    'session_data',
                    Types::BLOB,
                    [
                        'notNull' => true,
                        'default' => '',
                        'length' => 16777215
                    ]
                ],
                'max_lifetime' => [
                    'max_lifetime',
                    Types::INTEGER,
                    [
                        'notNull' => true,
                        'default' => 1800
                    ]
                ],
                'updated_at' => [
                    'updated_at',
                    Types::DATE_IMMUTABLE,
                    [
                        'notNull' => true,
                        'default' => 'CURRENT_TIMESTAMP',
                    ]
                ],
                'created_at' => [
                    'created_at',
                    Types::DATE_IMMUTABLE,
                    [
                        'notNull' => true,
                        'default' => 'CURRENT_TIMESTAMP',
                    ]
                ]
            ];
            foreach ($columns as $column) {
                $table->addColumn(...$column);
            }
            $table
                ->addUniqueIndex(['session_id', 'session_name'], 'selector_session')
                ->addIndex(['session_id'], 'session_id')
                ->addIndex(['session_name'], 'session_name')
                ->addIndex(['max_lifetime'], 'max_lifetime')
                ->addIndex(['created_at'], 'created_at')
                ->addIndex(['updated_at'], 'updated_at');
            if ($database->getDatabasePlatform() instanceof MySQLPlatform) {
                $table
                    ->getColumn('updated_at')
                    ->setColumnDefinition(
                        'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
                    );
            }
            $schema = new Schema([$table]);
            $database->multiQuery($schema->toSql($database->getDatabasePlatform()), [], $exception);
            if (!empty($exception)) {
                throw $exception;
            }
        }

        self::$exists = true;
        return true;
    }

    /**
     * @throws Exception
     */
    public function read(string $id) : string
    {
        if (!$this->sessionName || !is_string($this->sessionName)) {
            return '';
        }
        $database = $this->getContainer(Instance::class);
        $exp = $database->createExpressionBuilder();
        $result = $database
            ->createQueryBuilder()
            ->select(['session_data', 'max_lifetime'])
            ->where($exp->eq('session_id', ':id'))
            ->andWhere($exp->eq('session_name', ':name'))
            ->setParameters([
                'id' => $id,
                'name' => $this->sessionName
            ])
            ->setMaxResults(1)
            ->executeQuery();
        $data = $result->fetchAssociative();
        $result->free();
        if (!$data) {
            return '';
        }

        $max_lifetime = $data['max_lifetime'];
        if (is_numeric($max_lifetime)) {
            $this->setTTL((int) $max_lifetime);
        }

        return (string) $data['session_data'];
    }

    /**
     * @throws Exception
     */
    public function write(string $id, string $data): bool
    {
        if (!$this->sessionName || !is_string($this->sessionName)) {
            return false;
        }
        $database = $this->getContainer(Instance::class);
        $exp = $database->createExpressionBuilder();
        $result = $database
            ->createQueryBuilder()
            ->select(['1'])
            ->where($exp->eq('session_id', ':id'))
            ->andWhere($exp->eq('session_name', ':name'))
            ->setParameters(['id' => $id, 'name' => $this->sessionName])
            ->setMaxResults(1)
            ->executeQuery();
        $data = $result->fetchAssociative();
        if (!empty($data)) {
            $qb = $database
                ->createQueryBuilder()
                ->insert($this->getSessionTable())
                ->values([
                    'session_id' => ':session_id',
                    'session_name' => ':session_name',
                    'session_data' => ':session_data',
                    'max_lifetime' => ':max_lifetime',
                    'updated_at'    => ':updated_at',
                ])
                ->setParameters([
                    'session_id' => $id,
                    'session_name' => $this->sessionName,
                    'session_data' => $data,
                    'max_lifetime' => $this->getTTL(),
                    'updated_at'    => $this->nowExpression,
                ]);
        } else {
            $qb = $database
                ->createQueryBuilder()
                ->update($this->getSessionTable())
                ->set('session_data', ':session_data')
                ->set('max_lifetime', ':max_lifetime')
                ->set('updated_at', ':updated_at')
                ->where(
                    $exp->eq('session_name', ':session_name')
                )
                ->andWhere(
                    $exp->eq('session_id', ':session_id')
                )
                ->setParameters([
                    'session_id' => $id,
                    'session_name' => $this->sessionName,
                    'session_data' => $data,
                    'max_lifetime' => $this->getTTL(),
                    'updated_at'    => $this->nowExpression,
                ]);
        }
        $qb->executeQuery();
        return true;
    }

    /**
     * @throws Exception
     */
    public function updateTimeStamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}
