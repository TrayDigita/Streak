<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Imports
{
    /**
     * @var array<string,Import>
     */
    public readonly array $imports;

    /**
     * @param Import ...$imports
     */
    public function __construct(Import...$imports)
    {
        $importArray = [];
        foreach ($imports as $import) {
            $importArray[$import->name] = $import;
        }
        $this->imports = $importArray;
    }
}
