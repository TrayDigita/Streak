<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Import
{
    public readonly string $name;
    public readonly string $className;
    public readonly ?string $alias;

    public function __construct(string $name, string $className, ?string $alias = null)
    {
        $this->name = $name;
        $this->className = $className;
        $this->alias = $alias;
    }
}
