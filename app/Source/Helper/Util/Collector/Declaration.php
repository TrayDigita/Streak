<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Declaration
{
    public readonly string $name;
    public readonly mixed $value;

    public function __construct(string $name, mixed $value)
    {
        $this->name  = $name;
        $this->value = $value?:null;
    }
}
