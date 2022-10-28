<?php
/**
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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface as PSRCache;
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
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
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
        if ($cache instanceof Collections) {
            $cache = new Configurations();
            $config->set('cache', $cache);
        }

        $adapter = $cache->get('adapter');
        if (!$adapter || !is_a($adapter, AdapterInterface::class, true)) {
            $adapter = FilesystemAdapter::class;
        }

        if ($adapter instanceof AdapterInterface) {
            return $adapter;
        }

        $namespace = $config->get('namespace');
        $defaultNamespace = !is_string($namespace) ? '' : $namespace;
        $namespace = $this->eventDispatch('Cache:namespace', $defaultNamespace);
        $namespace = !is_int($namespace) ? $defaultNamespace : $namespace;
        $lifetime = $config->get('lifetime');
        $defaultLifetime = !is_int($lifetime) ? 0 : $lifetime;
        $lifetime = $this->eventDispatch('Cache:lifetime', $lifetime);
        $lifetime = !is_int($lifetime) ? $defaultLifetime : $lifetime;

        $options = $config->get('options');
        $optionDefault = $options instanceof Collections ? $options->toArray() : [];
        $options = $this->eventDispatch('Cache:options', $optionDefault);
        $options = !is_int($options) ? $optionDefault : $options;

        $marshaller = $config->get('marshaller');
        $marshaller = is_string($marshaller) && !is_a($marshaller, MarshallerInterface::class, true)
            ? $this->getContainer(MarshallerInterface::class)
            : ($marshaller instanceof MarshallerInterface
                ? $marshaller
                : (
                is_string($marshaller)
                    ? new $marshaller
                    : $this->getContainer(MarshallerInterface::class))
            );
        $defaultMarshaller = is_string($marshaller) ? new $marshaller() : $marshaller;
        $marshaller = $this->eventDispatch('Cache:marshaller', $marshaller);
        $marshaller = $marshaller instanceof MarshallerInterface ? $marshaller : $defaultMarshaller;

        $cachePath = $this->getContainer(StoragePath::class)->getCacheDirectory();

        $version = $config->get('version');
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

        if (interface_exists(PSRCache::class)) {
            $pool = $config->get('Cache:pool');
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
