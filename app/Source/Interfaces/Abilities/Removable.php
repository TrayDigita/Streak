<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Abilities;

interface Removable
{
    /**
     * @param string|int|float $name
     */
    public function remove(string|int|float $name);
}
