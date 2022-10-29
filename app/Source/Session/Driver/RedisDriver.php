<?php
/** @noinspection PhpComposerExtensionStubsInspection */
declare(strict_types=1);
namespace TrayDigita\Streak\Source\Session\Driver;

use Redis;
use Throwable;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Session\Abstracts\AbstractSessionDriver;

class RedisDriver extends AbstractSessionDriver
{
    protected Redis|false|null $redis = null;

    /**
     * @var ?string
     */
    protected ?string $sessionName = null;

    public static function isSupported(): bool
    {
        return class_exists(Redis::class);
    }

    public function getRedis(): bool|Redis
    {
        if ($this->redis === null) {
            $this->redis = false;
            if (self::isSupported()) {
                $host    = $this->eventDispatch('Redis:host', '127.0.0.1');
                $port    = $this->eventDispatch('Redis:port', 6379);
                $timeout = $this->eventDispatch('Redis:timeout', 0.0);
                try {
                    $this->redis = new Redis();
                    $this->redis->connect($host, $port, $timeout);
                } catch (Throwable) {
                }
            }
        }
        return $this->redis;
    }

    public function updateTimestamp(string $id, string $data) : bool
    {
        if (!$this->sessionName || !($redis = $this->getRedis())) {
            return false;
        }

        $id = $this->generateName($id);
        $redis->set($id, $data, $this->getTTL());
        return true;
    }

    protected function generateName(string $session_id) : string
    {
        return "sessions:$this->sessionName:$session_id:";
    }

    public function close() : bool
    {
        $this->sessionName = null;
        return true;
    }

    public function destroy(string $id) : bool
    {
        if (!$this->sessionName || !($redis = $this->getRedis())) {
            return false;
        }

        $id                = $this->generateName($id);
        $this->sessionName = null;
        $redis->unlink($id);
        return true;
    }

    public function gc($max_lifetime) : int|false
    {
        return 1;
    }

    public function open(string $path, string $name) : bool
    {
        $this->sessionName = $name;
        return (bool) $this->getRedis();
    }

    public function read(string $id) : string
    {
        if (!$this->sessionName || !($redis = $this->getRedis())) {
            return '';
        }

        $id     = $this->generateName($id);
        $result = $redis->get($id);
        if (!is_string($result)) {
            $redis->unlink($id);
            $result = '';
        }

        return $result;
    }

    public function write(string $id, string $data) : bool
    {
        if ($this->sessionName || !($redis = $this->getRedis())) {
            return false;
        }

        return $redis->set(
            $this->generateName($id),
            $data,
            $this->getTTL()
        );
    }
}
