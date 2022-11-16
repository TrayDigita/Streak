<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Property
{
    public readonly string $name;
    public readonly bool $nullable;
    public readonly bool $required;
    public readonly bool $hasDefaultValue;
    public readonly bool $hasType;
    public readonly array $defaultValue;
    public readonly array $type;
    public readonly string $visibility;
    public readonly bool $isPublic;
    public readonly bool $isPrivate;
    public readonly bool $isProtected;
    public readonly bool $isReadonly;
    public readonly bool $isStatic;

    /**
     * @param string $name
     * @param bool $nullable
     * @param bool $required
     * @param bool $hasDefaultValue
     * @param bool $hasType
     * @param array $defaultValue
     * @param array $type
     * @param string $visibility
     * @param bool $isPublic
     * @param bool $isPrivate
     * @param bool $isProtected
     * @param bool $isReadonly
     * @param bool $isStatic
     */
    public function __construct(
        string $name,
        bool $nullable,
        bool $required,
        bool $hasDefaultValue,
        bool $hasType,
        array $defaultValue,
        array $type,
        string $visibility,
        bool $isPublic,
        bool $isPrivate,
        bool $isProtected,
        bool $isReadonly,
        bool $isStatic
    ) {
        $this->name = $name;
        $this->nullable = $nullable;
        $this->required = $required;
        $this->hasDefaultValue = $hasDefaultValue;
        $this->hasType = $hasType;
        $this->defaultValue = $defaultValue;
        $this->type = $type;
        $this->isPrivate = $isPrivate;
        $this->visibility = $visibility;
        $this->isProtected = $isProtected;
        $this->isPublic = $isPublic;
        $this->isReadonly = $isReadonly;
        $this->isStatic = $isStatic;
    }
}
