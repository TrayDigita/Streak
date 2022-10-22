<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Abilities;

interface Replaceable
{
    public function replace(string|int|float $name, mixed $value);
}
