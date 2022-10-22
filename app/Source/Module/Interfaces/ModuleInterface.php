<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module\Interfaces;

use TrayDigita\Streak\Source\Interfaces\PriorityCallableInterface;

interface ModuleInterface extends PriorityCallableInterface
{
    public function getVersion() : string;
    public function getName() : string;
    public function getDescription() : string;
    public function initModule() : static;
}
