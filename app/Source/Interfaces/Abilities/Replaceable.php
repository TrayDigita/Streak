<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Abilities;

interface Replaceable
{
    /**
     * @param string|int|float $name
     * @param mixed $value
     */
    public function replace(string|int|float $name, mixed $value);
}
