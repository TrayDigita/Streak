<?php
/**
 * @noinspection PhpUndefinedNamespaceInspection
 * @noinspection PhpUndefinedClassInspection
 * @noinspection PhpComposerExtensionStubsInspection
 */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Session\Driver;

use Predis\Client;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Throwable;
use TrayDigita\Streak\Source\Cache;
use TrayDigita\Streak\Source\Configurations;
use TrayDigita\Streak\Source\Records\Collections;
use TrayDigita\Streak\Source\Session\Abstracts\AbstractSessionDriver;

class RedisDriver extends AbstractSessionDriver
{
    /**
     * @var RedisAdapter|false|null
     */
    protected RedisAdapter|null|false $redis = null;

    /**
     * @var ?string
     */
    protected ?string $sessionName = null;

    /**
     * @return bool
     */
    public static function isSupported(): bool
    {
        return extension_loaded('redis') || class_exists(Client::class);
    }

    /**
     * @return bool|RedisAdapter
     */
    public function getRedis(): bool|RedisAdapter
    {
        if ($this->redis === null) {
            $this->redis = false;
            if (self::isSupported()) {
                $sessions = $this->getContainer(Configurations::class)->get('cache');
                if (!$sessions instanceof Collections) {
                    $sessions = new Configurations([
                        'host' => '127.0.0.1',
                        'port' => 6379,
                        'timeout' => 0.0
                    ]);
                }
                $lifetime = $sessions->get('lifetime');
                $lifetime = is_numeric($lifetime) ? (int) $lifetime : $this->getTTL();
                try {
                    $redis = $this->getContainer(Cache::class)->tryGetRedis($sessions);
                    if ($redis) {
                        $defaultMarshaller = $sessions->get('marshaller');
                        if (is_string($defaultMarshaller)) {
                            if (!is_a($defaultMarshaller, MarshallerInterface::class, true)) {
                                $defaultMarshaller = $this->getContainer(MarshallerInterface::class);
                            } else {
                                $defaultMarshaller = new $defaultMarshaller();
                            }
                        } elseif (!$defaultMarshaller instanceof MarshallerInterface) {
                            $defaultMarshaller = $this->getContainer(MarshallerInterface::class);
                        }
                        $marshaller = $this->eventDispatch('Session:redis:marshaller', $defaultMarshaller);
                        $marshaller = $marshaller instanceof MarshallerInterface ? $marshaller : $defaultMarshaller;
                        unset($defaultMarshaller);
                        $this->redis = new RedisAdapter(
                            $redis,
                            'sessions',
                            $lifetime,
                            $marshaller
                        );
                    }
                } catch (Throwable) {
                }
            }
        }
        return $this->redis;
    }

    /**
     * @inheritDoc
     */
    public function updateTimestamp(string $id, string $data) : bool
    {
        return $this->write($id, $data);
    }

    /**
     * Generate session name
     *
     * @param string $session_id
     *
     * @return string
     */
    protected function generateName(string $session_id) : string
    {
        return "$this->sessionName:$session_id:";
    }

    /**
     * @inheritDoc
     */
    public function close() : bool
    {
        $this->sessionName = null;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function destroy(string $id) : bool
    {
        if (!$this->sessionName || !($redis = $this->getRedis())) {
            return false;
        }

        try {
            $id                = $this->generateName($id);
            $this->sessionName = null;
            return $redis->delete($id);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function gc($max_lifetime) : int|false
    {
        return 1;
    }

    /**
     * @inheritDoc
     */
    public function open(string $path, string $name) : bool
    {
        $this->sessionName = $name;
        return (bool) $this->getRedis();
    }

    /**
     * @inheritDoc
     */
    public function read(string $id) : string
    {
        if (!$this->sessionName || !($redis = $this->getRedis())) {
            return '';
        }

        try {
            $id     = $this->generateName($id);
            $item   = $redis->getItem($id);
            $result = $item->get();
            if (!is_string($result)) {
                $redis->delete($id);
                $result = '';
            }
        } catch (Throwable) {
            $result = '';
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function write(string $id, string $data) : bool
    {
        if ($this->sessionName || !($redis = $this->getRedis())) {
            return false;
        }
        try {
            $item = $redis->getItem($this->generateName($id));
            $item->set($data);
            $item->expiresAfter($this->getTTL());

            return $redis->save($item);
        } catch (Throwable) {
            return false;
        }
    }
}
