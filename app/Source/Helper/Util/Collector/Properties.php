<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Properties
{
    /**
     * @var array<string,Property>
     */
    public readonly array $properties;

    /**
     * @param Property ...$properties
     */
    public function __construct(Property...$properties)
    {
        $propertiesArray = [];
        foreach ($properties as $prop) {
            $propertiesArray[$prop->name] = $prop;
        }
        $this->properties = $propertiesArray;
    }
}
