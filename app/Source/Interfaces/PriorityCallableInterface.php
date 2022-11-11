<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces;

interface PriorityCallableInterface
{
    public function getPriority() : int;
}
