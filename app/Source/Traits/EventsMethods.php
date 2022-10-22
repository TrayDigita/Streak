<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use TrayDigita\Streak\Source\Events;

trait EventsMethods
{

    public function eventHas(string $name) : bool
    {
        $result = $this?->getContainer(Events::class)->has($name);
        return $result?:false;
    }

    public function eventIn(string $name) : bool
    {
        $result = $this?->getContainer(Events::class)->inEvent($name);
        return $result?:false;
    }

    public function eventAdd(string $name, callable $callable, int $priority = 10) : ?string
    {
        return $this?->getContainer(Events::class)->add($name, $callable, $priority);
    }

    public function eventAddOnce(string $name, callable $callable, int $priority = 10) : ?string
    {
        return $this?->getContainer(Events::class)->addOnce($name, $callable, $priority);
    }

    /**
     * @param string $name
     * @param callable|null $callable
     *
     * @return int
     */
    public function eventDispatched(string $name, ?callable $callable = null) : int
    {
        $res = $this?->getContainer(Events::class)->dispatched($name, $callable);
        return $res ?: 0;
    }

    /**
     * @param string $name
     * @param ...$args
     *
     * @return mixed
     */
    public function eventDispatch(string $name, ...$args) : mixed
    {
        $object = $this?->getContainer(Events::class);
        if ($object) {
            return $object->dispatch($name, ...$args);
        }
        return count($args) > 0 ? reset($args) : null;
    }
}
