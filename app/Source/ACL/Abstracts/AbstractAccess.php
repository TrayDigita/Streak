<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL\Abstracts;

use JetBrains\PhpStorm\Pure;
use TrayDigita\Streak\Source\ACL\Interfaces\AccessInterface;

abstract class AbstractAccess implements AccessInterface
{
    protected string $id;
    protected string $name = '';
    protected string $description = '';

    public function __construct()
    {
        $this->id   = $this->id?:get_class($this);
        $this->name = $this->name?:ucwords($this->id);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    #[Pure] final public function __toString(): string
    {
        return $this->getId();
    }

    /**
     * @param string $name
     *
     * @return string|null
     */
    #[Pure] public function __get(string $name)
    {
        return match ($name) {
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            default => null
        };
    }
}
