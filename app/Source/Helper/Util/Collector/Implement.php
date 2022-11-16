<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Implement
{
    public readonly string $name;
    public readonly ?string $alias;

    public function __construct(string $name, ?string $alias)
    {
        $this->name  = $name;
        $this->alias = $alias?:null;
    }
}
