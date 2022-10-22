<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Html;

use JetBrains\PhpStorm\Pure;
use TrayDigita\Streak\Source\Interfaces\Html\AttributeInterface;

class Attribute implements AttributeInterface
{
    /**
     * @var string
     */
    protected string $name;
    /**
     * @var string
     */
    protected string $value;

    /**
     * @param string $name
     * @param string|float|int|bool|null $value
     */
    public function __construct(string $name, string|float|int|bool|null $value)
    {
        $this->name = trim($name);
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        $this->value = (string) $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    #[Pure] public function build(): string
    {
        $result = $this->getName();
        if ($this->value !== '') {
            $result .= sprintf('="%s"', htmlspecialchars($this->getValue()));
        }

        return $result;
    }

    #[Pure] public function __toString(): string
    {
        return $this->build();
    }
}
