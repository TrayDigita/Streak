<?php
/* @noinspection PhpCSValidationInspection */
namespace TrayDigita\Streak\Source\Session;

use ArrayAccess;
use ReturnTypeWillChange;
use SessionHandler;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Generator\RandomString;
use TrayDigita\Streak\Source\Helper\Generator\UUID;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Interfaces\Collections\ToArray;
use TrayDigita\Streak\Source\Interfaces\ContainerizeInterface;
use TrayDigita\Streak\Source\Session\Driver\DefaultDriver;
use TrayDigita\Streak\Source\Session\Interfaces\SessionDriverInterface;
use TrayDigita\Streak\Source\Traits\Containerize;

class Handler extends SessionHandler implements ContainerizeInterface, ArrayAccess, ToArray, Startable
{
    /**
     * @var bool
     */
    protected bool $isDestroyed = false;

    /**
     * @var SessionDriverInterface
     */
    protected SessionDriverInterface $sessionDriver;

    /**
     * @var string
     */
    protected string $defaultSessionName;

    use Containerize;

    /**
     * @var Container
     * @readonly
     */
    public readonly Container $container;

    /**
     * @param Container $container
     * @param ?SessionDriverInterface $sessionDriver
     * @param bool $sessionAutoStart
     */
    public function __construct(
        Container $container,
        ?SessionDriverInterface $sessionDriver = null,
        protected bool $sessionAutoStart = false
    ) {
        $this->container = $container;
        $this->sessionDriver = $sessionDriver??new DefaultDriver($container);
        $this->defaultSessionName = $sessionDriver->getDefaultSessionName();
    }

    /**
     * @return bool
     */
    public function isSessionAutoStart(): bool
    {
        return $this->sessionAutoStart;
    }

    /**
     * @param bool $sessionAutoStart
     */
    public function setSessionAutoStart(bool $sessionAutoStart): void
    {
        $this->sessionAutoStart = $sessionAutoStart;
    }

    /**
     * Register Session Handler
     */
    public function register()
    {
        session_set_save_handler($this, true);
    }

    /**
     * @return bool
     */
    public function started(): bool
    {
        return $this->getSessionStatus() === PHP_SESSION_ACTIVE;
    }

    /**
     * @return bool
     */
    public function isSessionDisabled(): bool
    {
        return $this->getSessionStatus() === PHP_SESSION_DISABLED;
    }

    /**
     * @return string|false
     */
    public function getSessionId(): false|string
    {
        return session_id();
    }

    /**
     * @return int
     */
    public function getSessionStatus(): int
    {
        return session_status();
    }

    /**
     * @return array
     */
    public function &sessions(): array
    {
        $this->tryAutoStart();
        $session = [];
        if (isset($_SESSION)) {
            $session =& $_SESSION;
        }

        if (!is_array($session)) {
            $session = [];
        }

        return $session;
    }

    /**
     * @param string|null $name
     * @return bool
     */
    public function start(string $name = null): bool
    {
        if (!$this->started() && !$this->isSessionDisabled()) {
            $name = $name !== null ? $name : $this->defaultSessionName;
            $this->register();
            session_name($name);
            session_start();
        } elseif (is_string($name)) {
            session_name($name);
        }
        return true;
    }

    /**
     * @param string $name
     * @param $data
     */
    public function set(string $name, $data)
    {
        $this->tryAutoStart();
        $this[$name] = $data;
    }

    /**
     * @param string $name
     */
    public function remove(string $name)
    {
        unset($this[$name]);
    }

    /**
     * @return void
     */
    protected function tryAutoStart()
    {
        if ($this->sessionAutoStart === true && !$this->isDestroyed) {
            $this->start();
        }
    }

    /**
     * Recovery Default Session Handler
     * @noinspection PhpUnused
     */
    public function unregister()
    {
        session_set_save_handler(new SessionHandler(), true);
    }

    /**
     * @return SessionDriverInterface
     */
    public function getSessionDriver(): SessionDriverInterface
    {
        return $this->sessionDriver;
    }

    /**
     * @param SessionDriverInterface $sessionDriver
     */
    public function setSessionDriver(SessionDriverInterface $sessionDriver)
    {
        $this->sessionDriver = $sessionDriver;
    }

    /**
     * Close The session
     *
     * @return bool
     */
    public function close(): bool
    {
        return $this->getSessionDriver()->close();
    }

    /**
     * Create session ID
     * @return string
     */
    public function create_sid() : string
    {
        // $max = 128;
        $max = 72;
        $uuid = UUID::v4();
        $length = strlen($uuid);
        // add secure random
        if ($length < $max) {
            $hex = bin2hex(RandomString::bytes(64));
            $uuid .= "-". substr($hex, 0, $max-($length+1));
        }

        return $uuid;
    }

    public function validateId($session_id): bool
    {
        return is_string($session_id)
               && preg_match(
                   "~^[0-f0-9]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9-a-f]{4}-[0-9-a-f]{12}-[0-9a-f]{35}$~",
                   $session_id
               );
    }

    /**
     * Destroy The Session
     *
     * @param string $id
     *
     * @return bool
     */
    public function destroy($id): bool
    {
        return $this->getSessionDriver()->destroy($id);
    }

    /**
     * @param int $max_lifetime
     *
     * @return bool
     */
    #[ReturnTypeWillChange] public function gc($max_lifetime): bool
    {
        return $this->sessionDriver->gc($max_lifetime);
    }

    /**
     * @param string $path
     * @param string $name
     *
     * @return bool
     */
    public function open($path, $name): bool
    {
        $this->isDestroyed = false;
        return $this->sessionDriver->open($path, $name);
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public function read($id): string
    {
        $this->tryAutoStart();
        return $this->sessionDriver->read($id);
    }

    /**
     * @param string $id
     * @param string $data
     *
     * @return bool
     */
    public function write($id, $data): bool
    {
        $this->tryAutoStart();
        return $this->sessionDriver->write($id, $data);
    }

    /**
     * Update Timestamp
     *
     * @param string $session_id
     * @param string $session_data
     *
     * @return bool
     */
    public function updateTimestamp($session_id, $session_data) : bool
    {
        return $this->sessionDriver->updateTimeStamp($session_id, $session_data);
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        return $this->read($this->getSessionId());
    }

    public function offsetExists($offset): bool
    {
        return isset($this->sessions()[$offset]);
    }

    public function &offsetGet($offset)
    {
        return $this->sessions()[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->sessions()[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->sessions()[$offset]);
    }

    public function __set($name, $value)
    {
        $this[$name] = $value;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->sessions();
    }

    public function __unset($name)
    {
        unset($this[$name]);
    }
}
