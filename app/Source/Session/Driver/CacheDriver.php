<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Session\Driver;

use TrayDigita\Streak\Source\Cache;
use TrayDigita\Streak\Source\Session\Abstracts\AbstractSessionDriver;

class CacheDriver extends AbstractSessionDriver
{
    protected Cache|null $cache = null;
    protected ?string $sessionName = null;

    protected function afterConstruct()
    {
        $this->cache = $this->getContainer(Cache::class);
    }

    protected function generateId(string $id) : string
    {
        return sprintf('session_%s', md5($this->sessionName.$id));
    }

    public function close(): bool
    {
        $this->sessionName = null;
        return true;
    }

    public function destroy(string $id): bool
    {
        if (!$this->sessionName) {
            return false;
        }

        return $this->cache->deleteCache($this->generateId($id));
    }

    public function gc(int $max_lifetime): int|false
    {
        return 1;
    }

    public function open(string $path, string $name): bool
    {
        $this->sessionName = $name;
        return true;
    }

    public function read(string $id): string
    {
        $name = $this->generateId($id);
        $data = $this->cache->getCache($name);
        $value = $data->get();
        if (!is_string($value)) {
            $data->set('');
        }

        return $value;
    }

    public function write(string $id, string $data): bool
    {
        if (!$this->sessionName) {
            return false;
        }

        $name = $this->generateId($id);
        return $this->cache->saveCache($name, $data, $this->getTTL());
    }

    public function updateTimeStamp(string $id, string $data): bool
    {
        if (!$this->sessionName) {
            return false;
        }
        $id = $this->generateId($id);
        $this->cache->saveCache($id, $data, $this->getTTL());
        return true;
    }
}
