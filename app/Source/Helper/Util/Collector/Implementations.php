<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Implementations
{
    /**
     * @var array<string,Implement>
     */
    public readonly array $implements;

    /**
     * @param Implement ...$implements
     */
    public function __construct(Implement...$implements)
    {
        $implementsArray = [];
        foreach ($implements as $import) {
            $implementsArray[$import->name] = $import;
        }
        $this->implements = $implementsArray;
    }
}
