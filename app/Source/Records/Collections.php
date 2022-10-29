<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Records;

use ArrayAccess;
use JetBrains\PhpStorm\Pure;
use TrayDigita\Streak\Source\Interfaces\Collections\DataLists;
use TrayDigita\Streak\Source\Interfaces\Collections\GetterSetter;
use TrayDigita\Streak\Source\Interfaces\Abilities\Removable;
use TrayDigita\Streak\Source\Interfaces\Abilities\Replaceable;
use TrayDigita\Streak\Source\Interfaces\Collections\ToArray;

class Collections implements
    ArrayAccess,
    GetterSetter,
    Replaceable,
    DataLists,
    Removable,
    ToArray
{
    public function __construct(protected array $collections = [])
    {
    }

    /**
     * @param array $collections
     *
     * @return static
     */
    public static function fromArray(array $collections = []): static
    {
        return new static($collections);
    }

    public function keys(): array
    {
        return array_keys($this->collections);
    }

    public function all(): array
    {
        return $this->collections;
    }

    #[Pure] public function get(float|int|string $name, mixed $default = null)
    {
        return $this->has($name)
            ? $this->collections[$name]
            : $default;
    }

    public function set(float|int|string $name, mixed $value)
    {
        $this->collections[$name] = $value;
    }

    #[Pure] public function has(float|int|string $name): bool
    {
        return array_key_exists($name, $this->collections);
    }

    public function replace(float|int|string $name, mixed $value)
    {
        $this->set($name, $value);
    }

    public function remove(float|int|string $name)
    {
        unset($this->collections[$name]);
    }

    public function merge(iterable|Collections $collections): static
    {
        foreach ($collections as $key => $item) {
            $this->set($key, $item);
        }

        return $this;
    }

    /**
     * @param iterable $collections
     *
     * @return $this
     */
    public function mergeRecursive(iterable $collections): static
    {
        foreach ($collections as $key => $item) {
            if (!is_iterable($item)
                || ! $this->has($key)
                || !is_iterable($this->collections[$key]??null)
            ) {
                $this->set($key, $item);
                continue;
            }

            $data = $this->get($key);
            if ($data instanceof Collections) {
                $this->set($key, $data->merge($item));
                continue;
            }

            foreach ($item as $keyName => $v) {
                $data[$keyName] = $v;
            }
            $this->set($key, $data);
        }

        return $this;
    }

    /**
     * @return array
     */
    #[Pure] public function toArray() : array
    {
        $result = [];
        foreach ($this->all() as $key => $item) {
            if ($item instanceof Collections) {
                $result[$key] = $item->toArray();
                continue;
            }
            $result[$key] = $item;
        }
        return $result;
    }

    #[Pure] public function offsetExists($offset) : bool
    {
        return $this->has($offset);
    }

    #[Pure] public function offsetGet($offset) : mixed
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value) : void
    {
        $this->set($offset, $value);
    }

    public function offsetUnset($offset) : void
    {
        $this->remove($offset);
    }
}
