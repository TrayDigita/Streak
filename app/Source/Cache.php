<?php
/**
 * @noinspection PhpUndefinedNamespaceInspection
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpInternalEntityUsedInspection
 * @noinspection PhpComposerExtensionStubsInspection
 * @noinspection PhpUnused
 */
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use CouchbaseBucket;
use DateInterval;
use DateTimeInterface;
use Doctrine\DBAL\Exception;
use Memcached;
use PDO;
use Predis\ClientInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface as PSRCache;
use Redis;
use RedisArray;
use RedisCluster;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\CouchbaseBucketAdapter;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Database\Instance;
use TrayDigita\Streak\Source\Interfaces\Abilities\Clearable;
use TrayDigita\Streak\Source\Records\Collections;
use TrayDigita\Streak\Source\Traits\EventsMethods;

final class Cache extends AbstractContainerization implements Clearable, CacheItemPoolInterface
{
    use EventsMethods;

    /**
     * @var AdapterInterface
     */
    public readonly AdapterInterface $adapter;

    /**
     * @var bool
     */
    private bool $init = false;

    /**
     * @var array
     */
    // private array $driverArguments = [];

    /**
     * @var string
     */
    private string $driverClass = FilesystemAdapter::class;

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->adapter = $this->cacheAdapterInit();
    }

    /**
     * @return AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @param string|CacheItemInterface $key
     *
     * @return bool
     */
    public function deleteCache(string|CacheItemInterface $key) : bool
    {
        if ($key instanceof CacheItemInterface) {
            $key = $key->getKey();
        }
        try {
            return $this->getAdapter()->deleteItem($key);
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteCaches(string|CacheItemInterface|array $key) : bool
    {
        if ($key instanceof CacheItemInterface) {
            $key = $key->getKey();
        }
        $key = is_string($key) ? [$key] : $key;
        foreach ($key as $k => $item) {
            if (is_string($item)) {
                continue;
            }
            if ($item instanceof CacheItemInterface) {
                $key[$k] = $item->getKey();
                continue;
            }
            unset($key[$k]);
        }
        try {
            return $this->getAdapter()->deleteItems($key);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param string|CacheItemInterface $key
     *
     * @return bool
     */
    public function hasCache(string|CacheItemInterface $key) : bool
    {
        if ($key instanceof CacheItemInterface) {
            $key = $key->getKey();
        }
        try {
            return $this->getAdapter()->hasItem($key);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param string|CacheItemInterface $key
     * @param null $error
     *
     * @return ?CacheItemInterface
     */
    public function getCache(string|CacheItemInterface $key, &$error = null) : ?CacheItemInterface
    {
        if ($key instanceof CacheItemInterface) {
            $key = $key->getKey();
        }
        try {
            return $this->getAdapter()->getItem($key);
        } catch (Throwable $e) {
            $error = $e;
            return null;
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int|DateInterval|null $expiredAfter
     * @param DateTimeInterface|null $expiration
     *
     * @return bool
     */
    public function saveCache(
        string $key,
        mixed $value,
        int|DateInterval|null $expiredAfter = null,
        DateTimeInterface $expiration = null
    ): bool {
        try {
            $cacheItem = $this->getAdapter()->getItem($key);
        } catch (Throwable) {
            return false;
        }
        $cacheItem->set($value);
        if ($expiredAfter !== null) {
            $cacheItem->expiresAfter($expiredAfter);
        }
        if ($expiration) {
            $cacheItem->expiresAt($expiration);
        }
        return $this->save($cacheItem);
    }

    /**
     * @param CacheItemInterface $item
     *
     * @return bool
     */
    public function saveItem(CacheItemInterface $item) : bool
    {
        return $this->save($item);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function cacheAdapterInit() : AdapterInterface
    {
        if ($this->init) {
            return $this->getAdapter();
        }

        $this->init = true;
        $config = $this->getContainer(Configurations::class);
        $cache = $config->get('cache');
        if (!$cache instanceof Configurations) {
            $cache = new Configurations(
                $cache instanceof Collections
                    ? $cache->toArray()
                    : []
            );
            $config->set('cache', $cache);
        }

        $adapter = $cache->get('adapter');
        if (!$adapter || !is_a($adapter, AdapterInterface::class, true)) {
            $adapter = FilesystemAdapter::class;
        }

        if ($adapter instanceof AdapterInterface) {
            return $adapter;
        }

        $namespace = $cache->get('namespace');
        $defaultNamespace = !is_string($namespace)
            || trim($namespace) === ''
            || preg_match('~[^-+_.A-Za-z0-9]~', trim($namespace))
            ? 'caches'
            : trim($namespace);
        $namespace = $this->eventDispatch('Cache:namespace', $defaultNamespace);
        $namespace = !is_string($namespace)
            || trim($namespace) === ''
            || preg_match('~[^-+_.A-Za-z0-9]~', trim($namespace))
            ? $defaultNamespace : trim($namespace);

        $lifetime = $cache->get('lifetime');
        $defaultLifetime = !is_int($lifetime) ? 0 : $lifetime;
        $lifetime = $this->eventDispatch('Cache:lifetime', $lifetime);
        $lifetime = !is_int($lifetime) ? $defaultLifetime : $lifetime;

        $options = $cache->get('options');
        $optionDefault = $options instanceof Collections ? $options->toArray() : [];
        $options = $this->eventDispatch('Cache:options', $optionDefault);
        $options = !is_int($options) ? $optionDefault : $options;

        $marshaller = $cache->get('marshaller');
        if (is_string($marshaller)) {
            if (!is_a($marshaller, MarshallerInterface::class, true)) {
                $marshaller = $this->getContainer(MarshallerInterface::class);
            } else {
                $marshaller = new $marshaller();
            }
        } elseif (!$marshaller instanceof MarshallerInterface) {
            $marshaller = $this->getContainer(MarshallerInterface::class);
        }

        $defaultMarshaller = $marshaller;
        $marshaller = $this->eventDispatch('Cache:marshaller', $marshaller);
        $marshaller = $marshaller instanceof MarshallerInterface ? $marshaller : $defaultMarshaller;

        $cachePath = $this->getContainer(StoragePath::class)->getCacheDirectory();

        $version = $cache->get('version');
        $version = !is_string($version) ? null : $version;
        $maxItems = (int) $this->eventDispatch('Cache:maxItems', 100);
        $maxItems = $maxItems < 10 ? 10 : (
            $maxItems > 1000 ? 1000 : $maxItems
        );
        $storeSerialize = (bool) $this->eventDispatch('Cache:storeSerialize', true);
        $maxLifetime = (float) $this->eventDispatch('Cache:storeSerialize', 0.0);

        // set config
        $cache->set('namespace', $namespace);
        $cache->set('lifetime', $lifetime);
        $cache->set('options', $options);
        $cache->set('marshaller', $marshaller);
        $cache->set('path', $cachePath);
        $cache->set('version', $version);
        $adapterArgs = [
            ArrayAdapter::class => [
                $lifetime,
                $storeSerialize,
                $maxLifetime,
                $maxItems
            ],
            PdoAdapter::class => [
                null,
                $namespace,
                $lifetime,
                $options,
                $marshaller
            ],
            DoctrineDbalAdapter::class => [
                null,
                $namespace,
                $lifetime,
                $options,
                $marshaller
            ],
            MemcachedAdapter::class => [
                Memcached::class,
                $namespace,
                $lifetime,
                $marshaller
            ],
            CouchbaseBucketAdapter::class => [
                CouchbaseBucket::class,
                $namespace,
                $lifetime,
                $marshaller
            ],
            FilesystemAdapter::class => [
                $namespace,
                $lifetime,
                $cachePath,
                $marshaller
            ],
            ApcuAdapter::class => [
                $namespace,
                $lifetime,
                $version,
                $marshaller
            ],
            PhpFilesAdapter::class => [
                $namespace,
                $lifetime,
                $cachePath
            ]
        ];
        if (class_exists(Redis::class)) {
            if ($adapter === RedisAdapter::class) {
                $redis = $this->trygetRedis($config);
                if ($redis) {
                    $adapterArgs[RedisAdapter::class] = [
                        $redis,
                        $namespace,
                        $lifetime,
                        $marshaller
                    ];
                } else {
                    $adapter = FilesystemAdapter::class;
                }
            }
        }
        if (interface_exists(PSRCache::class)) {
            $pool = $cache->get('pool');
            $pool = $pool instanceof PSRCache ? $pool : null;
            $pool = $this->eventDispatch('Cache:pool', $pool);
            if ($pool instanceof PSRCache) {
                $adapterArgs[Psr16Adapter::class] = [
                    $pool,
                    $namespace,
                    $lifetime
                ];
            }
        }

        $adapter = isset($adapterArgs[$adapter]) ? $adapter : FilesystemAdapter::class;
        $ref = new ReflectionClass($adapter);
        if ($ref->hasMethod('isSupported')) {
            if ($ref->getMethod('isSupported')->isPublic()
               && $ref->getMethod('isSupported')->isStatic()
            ) {
                $adapter = call_user_func([$adapter, 'isSupported'])
                    ? $adapter
                    : FilesystemAdapter::class;
            } else {
                $adapter = FilesystemAdapter::class;
            }
        }

        if (($doctrine = $adapter === DoctrineDbalAdapter::class)
            || $adapter === PdoAdapter::class
        ) {
            $database = $this->getContainer(Instance::class);
            if ($doctrine) {
                $adapterArgs[DoctrineDbalAdapter::class][0] = $database->getConnection();
            } elseif ($database->getNativeConnection() instanceof PDO) {
                $adapterArgs[PdoAdapter::class][0] = $database->getNativeConnection();
            } else {
                $adapter = FilesystemAdapter::class;
            }
        }

        $adapterArgs = $adapterArgs[$adapter];
        $this->driverClass = $adapter;
        // $this->driverArguments = $adapterArgs;

        return new $adapter(...$adapterArgs);
    }

    /**
     * @param Collections $config
     *
     * @return RedisClusterProxy|Redis|RedisArray|RedisCluster|ClientInterface|RedisProxy|null
     */
    public function tryGetRedis(
        Collections $config
    ): RedisClusterProxy|Redis|RedisArray|RedisCluster|ClientInterface|null|RedisProxy {
        $redis = $config->get('redis')??null;
        if (! $redis instanceof Redis &&
            ! $redis instanceof RedisArray &&
            ! $redis instanceof RedisCluster
        ) {
            $dsn = $config->get('dsn')??null;
            $dsn = !is_string($dsn) ? null : $dsn;
            $options = $config->get('options')??[];
            $options = !is_array($options) ? [] : $options;
            if ($dsn && !preg_match('~^rediss?://~', $dsn)) {
                $socket = $config->get('socket') ?? null;
                $dsn = $socket && file_exists($socket)
                       && filetype($socket) === 'socket'
                    ? "redis://$socket"
                    : null;
            }
            if (!$dsn) {
                $host = $config->get('host');
                $host = is_string($host) ? $host : '127.0.0.1';
                $port = $config->get('port');
                $port = !is_numeric($port) ? (int) $port : 6379;
                $dsn = "redis://$host:$port";
            }
            $timeout = $config->get('timeout');
            $timeout = is_numeric($timeout) ? (float) $timeout : 0.0;
            $persistent_id = $config->get('persistent_id');
            $persistent_id = !is_string($persistent_id) ? $persistent_id : '';
            $retry_interval = $config->get('retry_interval');
            $retry_interval = !is_numeric($retry_interval) ? (int) $retry_interval : 0;
            $read_timeout = $config->get('read_timeout');
            $read_timeout = !is_numeric($read_timeout) ? (int) $read_timeout : 0;
            $options['timeout'] = !is_numeric($options['timeout']??null)
                ? $timeout
                : (float) $options['timeout'];
            $options['persistent_id'] = !is_string($options['persistent_id']??null)
                ? $persistent_id
                : (string) $options['persistent_id'];
            $options['retry_interval'] = !is_numeric($options['retry_interval']??null)
                ? $retry_interval
                : (int) $options['retry_interval'];
            $options['read_timeout'] = !is_numeric($options['read_timeout']??null)
                ? $read_timeout
                : (int) $options['read_timeout'];
            try {
                return RedisAdapter::createConnection(
                    $dsn,
                    $options
                );
            } catch (Throwable) {
            }
        }

        return null;
    }

    /**
     * @return array
     */
    /*public function getDriverArguments(): array
    {
        return $this->driverArguments;
    }*/

    /**
     * @return string
     */
    public function getDriverClass(): string
    {
        return $this->driverClass;
    }

    public function getItem(string $key): CacheItemInterface
    {
        return $this->getAdapter()->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->getAdapter()->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->getAdapter()->hasItem($key);
    }

    public function deleteItem(string $key): bool
    {
        return $this->getAdapter()->deleteItem($key);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->getAdapter()->saveDeferred($item);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->getAdapter()->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->getAdapter()->save($item);
    }

    public function commit(): bool
    {
        return $this->getAdapter()->commit();
    }

    public function clear() : bool
    {
        return $this->getAdapter()->clear();
    }
}
