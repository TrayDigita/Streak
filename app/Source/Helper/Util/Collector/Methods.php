<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Methods
{
    /**
     * @var array<string,Method>
     */
    public readonly array $method;

    /**
     * @param Method ...$methods
     */
    public function __construct(Method...$methods)
    {
        $methodNow = [];
        foreach ($methods as $method) {
            $methodNow[strtolower($method->name)] = $method;
        }
        $this->method = $methodNow;
    }

    /**
     * @param string $method
     *
     * @return ?Method
     */
    public function getMethod(string $method) : ?Method
    {
        return $this->method[strtolower(trim($method))]??null;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasMethod(string $name) : bool
    {
        return $this->getMethod($name) !== null;
    }
}
