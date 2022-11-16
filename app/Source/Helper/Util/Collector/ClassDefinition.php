<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class ClassDefinition
{
    public readonly ?string $name;
    public readonly ?string $namespace;
    public readonly ?string $fullName;
    public readonly bool $isAnonymous;
    public readonly bool $isChild;
    public readonly bool $isFinal;
    public readonly bool $isAbstract;
    public readonly bool $isInterface;
    public readonly bool $isTrait;
    public readonly bool $hasParent;
    public readonly bool $hasInterface;
    public readonly bool $hasConstructor;
    public readonly ?string $constructor;
    public readonly Declares $declares;
    public readonly Imports $imports;
    public readonly Implementations $implements;
    public readonly ?Extend $extend;
    public readonly Properties $properties;
    public readonly Methods $methods;

    public function __construct(
        ?string $name,
        ?string $fullName,
        ?string $namespace,
        bool $isAnonymous,
        bool $isFinal,
        bool $isAbstract,
        bool $isInterface,
        bool $isTrait,
        ?string $constructor,
        Declares $declares,
        Imports $imports,
        Implementations $implementations,
        ?Extend $extend,
        Properties $properties,
        Methods $methods,
    ) {
        $this->name = $name;
        $this->namespace = $namespace;
        $this->fullName = $fullName;
        $this->isAnonymous = $isAnonymous;
        $this->isFinal = $isFinal;
        $this->isAbstract = $isAbstract;
        $this->isTrait = $isTrait;
        $this->isInterface = $isInterface;
        $this->declares = $declares;
        $this->imports = $imports;
        $this->constructor = $constructor;
        $this->hasConstructor = !empty($constructor);
        $this->implements = $implementations;
        $this->extend = $extend;
        $this->properties = $properties;
        $this->methods = $methods;
        $this->isChild = $extend !== null;
        $this->hasParent = $this->isChild;
        $this->hasInterface = !empty($this->implements->implements);
    }

    /**
     * @param string|object $className
     * @return bool
     */
    public function isSubClassOf(string|object $className) : bool
    {
        $classNameFull = $this->fullName;
        $parent = $this->extend?->fullName;
        if (!$classNameFull || !$parent) {
            return false;
        }
        if (strtolower($parent) === strtolower($className) || (
                class_exists($parent) && is_subclass_of($parent, $className)
            )) {
            return true;
        }

        return is_subclass_of($classNameFull, $className);
    }
}
