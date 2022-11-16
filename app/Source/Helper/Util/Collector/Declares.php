<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Declares
{
    /**
     * @var array<string,Declaration>
     */
    public readonly array $declares;

    /**
     * @param Declaration ...$declares
     */
    public function __construct(Declaration...$declares)
    {
        $this->declares = $declares;
    }
}
