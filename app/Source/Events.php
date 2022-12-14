<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;

final class Events extends AbstractContainerization
{
    /**
     * @var array
     */
    protected array $events = [];

    /**
     * @var array
     */
    protected array $dispatched = [];

    /**
     * @var array<int, array<string, int>>
     */
    protected array $dispatchOne = [];

    /**
     * @var array
     */
    protected array $currentEvents = [];

    /**
     * @var array
     */
    protected array $storedEventsKey = [];

    /**
     * @param callable $callable
     *
     * @return string|false
     */
    public function buildUniqueId(callable $callable) : string|false
    {
        if (is_string($callable)) {
            return $callable;
        }

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        if (is_object($callable)) {
            // Closures are currently implemented as objects.
            $callable = [$callable, ''];
        } else {
            $callable = (array)$callable;
        }

        if (is_object(reset($callable))) {
            // Object class calling.
            return spl_object_hash(reset($callable)) . next($callable);
        } elseif (is_string(reset($callable))) {
            // Static calling.
            return reset($callable) . '::' . next($callable);
        }

        return false;
    }

    /**
     * Add event
     *
     * @param string $name
     * @param callable $callable
     * @param int $priority
     *
     * @return string
     */
    public function add(
        string $name,
        callable $callable,
        int $priority = 10
    ): string {
        $id = $this->buildUniqueId($callable);
        $this->events[$name][$priority][$id][] = $callable;
        $this->storedEventsKey[$name][$id][$priority] = true;
        return $id;
    }

    /**
     * @param string $name
     * @param ?callable $callable
     *
     * @return bool
     */
    public function has(string $name, callable $callable = null) : bool
    {
        if (!isset($this->storedEventsKey[$name]) || ! $callable) {
            return false;
        }

        $id = $this->buildUniqueId($callable);
        return !empty($this->storedEventsKey[$name][$id]);
    }

    /**
     * @param string $name
     * @param callable $callable
     * @param int $priority
     *
     * @return string
     */
    public function addOnce(
        string $name,
        callable $callable,
        int $priority = 10
    ): string {
        $id = $this->buildUniqueId($callable);
        $this->events[$name][$priority][$id][] = $callable;
        $this->dispatchOne[$name][$priority] = $id;
        $this->storedEventsKey[$name][$id][$priority] = true;
        return $id;
    }

    /**
     * Count the event by event name
     *
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return int
     */
    public function countEvents(string $name, ?callable $callable = null, ?int $priority = null) : int
    {
        $count = 0;
        $hash = $callable !== null ? $this->buildUniqueId($callable) : null;
        foreach (($this->events[$name]??[]) as $prior => $callables) {
            if ($priority !== null && $prior !== $priority) {
                continue;
            }
            if ($hash === null) {
                $count += count($callables);
                continue;
            }
            foreach ($callables as $id => $fn) {
                if ($hash == $id) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Remove the event
     *
     * @param string $name
     * @param callable|null $callable
     * @param int|null $priority
     *
     * @return int
     */
    public function remove(string $name, ?callable $callable = null, ?int $priority = null) : int
    {
        $count = 0;
        $hash = $callable !== null ? $this->buildUniqueId($callable) : null;
        foreach ($this->events[$name] as $prior => $callables) {
            if ($priority !== null && $prior !== $priority) {
                continue;
            }
            if ($hash === null) {
                $count += count($callables);
                unset($this->events[$name][$prior]);
                array_map(
                    function ($id) use ($name, $prior) {
                        unset($this->storedEventsKey[$name][$id][$prior]);
                        if (empty($this->storedEventsKey[$name][$id])) {
                            unset($this->storedEventsKey[$name][$id]);
                        }
                    },
                    array_keys($callables)
                );
                continue;
            }

            foreach ($callables as $id => $callable) {
                if ($hash !== $id) {
                    continue;
                }
                $count++;
                unset($this->events[$name][$prior][$id]);
                if (isset($this->storedEventsKey[$name][$id])) {
                    unset($this->storedEventsKey[$name][$id][$prior]);
                    if (empty($this->storedEventsKey[$name][$id])) {
                        unset($this->storedEventsKey[$name][$id]);
                    }
                }
            }

            if (empty($this->events[$name][$prior])) {
                unset($this->events[$name][$prior]);
            }
        }

        if (empty($this->storedEventsKey[$name])) {
            unset($this->storedEventsKey[$name]);
        }
        if (empty($this->events[$name])) {
            unset($this->events[$name]);
        }
        return $count;
    }

    /**
     * @return ?string
     * @noinspection PhpUnused
     */
    public function getCurrentEvent(): ?string
    {
        end($this->currentEvents);
        return key($this->currentEvents)?:null;
    }

    /**
     * @param string|null $name
     *
     * @return bool
     */
    public function inEvent(?string $name = null) : bool
    {
        return $name !== null ? isset($this->currentEvents[$name]) : !empty($this->currentEvents);
    }

    /**
     * @return array
     * @noinspection PhpUnused
     */
    public function getCurrentEvents(): array
    {
        return $this->currentEvents;
    }

    /**
     * Dispatch the Event
     *
     * @param string $name event name
     * @param ...$args
     *
     * @return mixed
     */
    public function dispatch(string $name, ...$args): mixed
    {
        $recordName = sprintf('Events:dispatch[%s]', $name);
        $record = $this->getContainer(Benchmark::class);
        $val = reset($args)??null;
        $key = key($args);
        if (!isset($this->events[$name])) {
            return $val;
        }

        ksort($this->events[$name], SORT_ASC);
        $dispatched = false;
        $this->currentEvents[$name] = true;
        foreach ($this->events[$name] as $priority => $callableCollections) {
            foreach ($callableCollections as $id => $callables) {
                foreach ($callables as $callable) {
                    if (!$dispatched) {
                        $dispatched = true;
                        $record->start($recordName);
                    }

                    $val = $callable(...$args);
                    if (!isset($this->dispatched[$name])) {
                        $this->dispatched[$name] = 0;
                    }
                    $this->dispatched[$name]++;

                    array_shift($args);
                    if (is_int($key)) {
                        array_unshift($args, $val);
                    } else {
                        $args = array_merge([$key => $val], $args);
                    }
                }

                if (isset(
                    $this->dispatchOne[$name],
                ) && $this->dispatchOne[$name][$priority] === $id) {
                    unset(
                        $this->events[$name][$priority][$id],
                        $this->dispatchOne[$name][$priority],
                        $this->storedEventsKey[$name][$id][$priority]
                    );
                    if (empty($this->storedEventsKey[$name][$id])) {
                        unset($this->storedEventsKey[$name][$id]);
                    }
                }
            }
        }

        if (empty($this->dispatchOne[$name])) {
            unset($this->dispatchOne[$name]);
        }
        if (empty($this->storedEventsKey[$name])) {
            unset($this->storedEventsKey[$name]);
        }

        unset($this->currentEvents[$name]);
        $dispatched && $record->stop($recordName);
        return $val;
    }

    /**
     * @param string $eventName
     * @param callable|null $callable
     *
     * @return int
     */
    public function dispatched(string $eventName, ?callable $callable = null) : int
    {
        if (!isset($this->dispatched[$eventName])) {
            return 0;
        }
        $hash = $callable ? $this->buildUniqueId($callable) : null;
        $count = 0;
        foreach ($this->dispatched[$eventName] as $id => $total) {
            if ($hash === null || $id === $hash) {
                $count += $total;
            }
        }
        return $count;
    }
}
