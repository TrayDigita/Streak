<?php
/**
 * @noinspection PhpCSValidationInspection
 * @noinspection PhpUnused
 */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Session;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Configurations;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Records\Collections;
use TrayDigita\Streak\Source\Records\DeepArrayCollections;
use TrayDigita\Streak\Source\Session\Abstracts\AbstractSessionDriver;
use TrayDigita\Streak\Source\Session\Driver\FileDriver;
use TrayDigita\Streak\Source\Traits\EventsMethods;

/**
 * @mixin AbstractSessionDriver
 */
class Sessions extends AbstractContainerization implements
    SessionHandlerInterface,
    SessionUpdateTimestampHandlerInterface
{
    use EventsMethods;

    /**
     * @var Handler
     */
    protected Handler $handler;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $config = $this->getContainer(Configurations::class);
        $session = $config->get('session');
        if (!$session instanceof Collections) {
            $session = new DeepArrayCollections();
            $config->set('session', $session);
        }
        $session_name = $session->get('name');
        $session_name = !is_string($session_name) ? session_name() : $session_name;
        $session->set('name', $session_name);

        $session_name = $this->eventDispatch('Session:name', $session_name);
        $session_name = !is_string($session_name) || trim($session_name) === ''
            ? session_name()
            : $session_name;
        // set session name
        session_name($session_name);

        $session_driver = $session->get('driver');
        if (!$session_driver instanceof AbstractSessionDriver) {
            $session_driver = is_string($session_driver) ? $session_driver : FileDriver::class;
            if (!is_a($session_driver, AbstractSessionDriver::class, true)) {
                $session_driver = FileDriver::class;
            }
            $session->set('driver', $session_driver);
            $session_driver = new $session_driver($config);
        }

        // AUTO START
        $autoStart = $session->get('autostart');
        $autoStart = (bool) $autoStart;
        $session->set('autostart', $autoStart);
        $autoStart = (bool) $this->eventDispatch('Session:autostart', $autoStart);

        $this->handler = new Handler($container, $session_driver, $autoStart);
        $this->handler->getSessionDriver()->setDefaultSessionName($session_name);
        $this->register();
    }

    public function register()
    {
        $this->handler->register();
    }

    public function unregister()
    {
        $this->handler->unregister();
    }

    /**
     * @return string
     * phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public function create_sid() : string
    {
        return $this->handler->create_sid();
    }

    public function close(): bool
    {
        return $this->handler->close();
    }

    public function destroy($id): bool
    {
        return $this->handler->destroy($id);
    }

    public function gc($max_lifetime): int|false
    {
        return $this->handler->gc($max_lifetime);
    }

    public function open($path, $name): bool
    {
        return $this->handler->open($path, $name);
    }

    public function read($id): string
    {
        return $this->handler->read($id);
    }

    public function write($id, $data): bool
    {
        return $this->handler->write($id, $data);
    }

    public function validateId($id) : bool
    {
        return $this->handler->validateId($id);
    }

    public function updateTimestamp($id, $data): bool
    {
        return $this->handler->updateTimestamp($id, $data);
    }

    /**
     * @return Handler
     */
    public function getHandler() : Handler
    {
        return $this->handler;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this, $name], $arguments);
    }
}
