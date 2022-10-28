<?php
namespace TrayDigita\Streak\Source\Session\Abstracts;

use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Session\Interfaces\SessionDriverInterface;
use TrayDigita\Streak\Source\Traits\EventsMethods;

abstract class AbstractSessionDriver extends AbstractContainerization implements SessionDriverInterface
{
    use EventsMethods;

    protected ?int $ttl = null;

    /**
     * @var string
     */
    protected string $defaultSessionName = 'PHPSSID';

    /**
     * @return bool
     */
    public static function isSupported() : bool
    {
        return true;
    }

    final public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->defaultSessionName = session_name();
        $this->afterConstruct();
    }

    protected function afterConstruct()
    {
        // do override
    }

    /**
     * @param string $session_name
     */
    public function setDefaultSessionName(string $session_name)
    {
        $this->defaultSessionName = $session_name;
    }

    /**
     * @return string
     */
    public function getDefaultSessionName(): string
    {
        return $this->defaultSessionName;
    }

    public function setTTL(int $max_lifetime)
    {
        $this->ttl = $max_lifetime;
    }

    public function getTTL(): int
    {
        if (is_int($this->ttl)) {
            return $this->ttl;
        }

        $this->ttl = (int) ini_get('session.gc_maxlifetime');
        return $this->ttl;
    }
}
