<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Extend
{
    public readonly string $fullName;
    public readonly string $name;
    public readonly ?string $alias;

    public function __construct(string $fullName, string $name, ?string $alias)
    {
        $this->fullName = $fullName;
        $this->name  = $name;
        $this->alias = $alias?:null;
    }
}
