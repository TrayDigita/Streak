<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Collections;

interface GetterSetter
{
    /**
     * @param float|int|string $name
     * @param mixed $default
     */
    public function get(float|int|string $name, mixed $default = null);

    /**
     * @param float|int|string $name
     * @param mixed $value
     */
    public function set(float|int|string $name, mixed $value);

    /**
     * @param float|int|string $name
     *
     * @return bool
     */
    public function has(float|int|string $name) : bool;
}
