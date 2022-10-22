<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Abilities;

interface Startable
{
    /**
     * start the process
     */
    public function start();

    /**
     * @return bool
     */
    public function started(): bool;
}
