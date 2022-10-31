<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use TrayDigita\Streak\Source\Events;

trait EventsMethods
{
    public function eventCurrent() : ?string
    {
        return $this?->getContainer(Events::class)->getCurrentEvent();
    }

    public function eventCurrents() : array
    {
        return $this?->getContainer(Events::class)->getCurrentEvents()?:[];
    }

    public function eventRemove(string $name, ?callable $callable = null, ?int $priority = null) : int
    {
        return $this?->getContainer(Events::class)->remove($name, $callable, $priority)?:0;
    }

    public function eventHas(string $name) : bool
    {
        return $this?->getContainer(Events::class)->has($name)??false;
    }

    public function eventIn(string $name) : bool
    {
        return $this?->getContainer(Events::class)->inEvent($name)??false;
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
        $res = $this?->getContainer(Events::class)->dispatched($name, $callable)??0;
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
