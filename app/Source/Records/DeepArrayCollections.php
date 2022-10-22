<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Records;

use JetBrains\PhpStorm\Pure;
use ReturnTypeWillChange;

class DeepArrayCollections extends Collections
{
    public function __construct(iterable $collections = [])
    {
        foreach ($collections as $key => $collection) {
            $collections[$key] = is_iterable($collection)
                ? new static($collection)
                : $collection;
        }
        parent::__construct($collections);
    }

    #[ReturnTypeWillChange] public function set(float|int|string $name, mixed $value): void
    {
        $this->collections[$name] = is_iterable($value)
            ? new static($value)
            : $value;
    }

    /**
     * @param float|int|string $name
     * @param mixed|null $default
     *
     * @return mixed
     */
    #[Pure] public function get(float|int|string $name, mixed $default = null): mixed
    {
        return parent::get($name, $default);
    }
}
