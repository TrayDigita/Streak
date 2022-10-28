<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

use ArrayIterator;
use IteratorAggregate;
use JetBrains\PhpStorm\Pure;
use Serializable;
use Traversable;

class ResultParser implements IteratorAggregate, Serializable
{
    const DECLARE_NAME = 'declare';
    const NAMESPACE_NAME = 'namespace';
    const USE_NAME = 'use';
    const IMPORT_NAME = self::USE_NAME;
    const CLASS_NAME = 'class';

    /**
     * @var array
     */
    public readonly array $definitions;

    /**
     * ResultParserCollector constructor.
     * @param array $declare
     * @param string|null $namespace
     * @param array $imports
     * @param array $class
     */
    public function __construct(
        array $declare = [],
        ?string $namespace = null,
        array $imports = [],
        array $class = []
    ) {
        $this->definitions = [
            self::DECLARE_NAME => $declare,
            self::NAMESPACE_NAME => $namespace,
            self::IMPORT_NAME => $imports,
            self::CLASS_NAME => $class,
        ];
    }

    /**
     * @return bool
     */
    #[Pure] public function isInstantiate() : bool
    {
        if ((
                $this->isFinalClass()
                || !$this->isAbstract() && ! $this->isTrait() && ! $this->isInterface()
            ) && ( ! $this->getConstructorName()
                || $this->getMethod(
                    $this->getConstructorName()
                )['isPublic']??null
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    public function getDefinitions() : array
    {
        return $this->definitions;
    }

    /**
     * @return Traversable
     */
    public function getIterator() : Traversable
    {
        return new ArrayIterator($this->getDefinitions());
    }

    /**
     * @return bool
     */
    #[Pure] public function hasNameSpace() : bool
    {
        return $this->getNameSpace() !== null;
    }

    /**
     * @return string|null
     */
    public function getNameSpace() : ?string
    {
        return $this->{self::NAMESPACE_NAME};
    }

    /**
     * @return array
     */
    public function getClassDefinition() : array
    {
        return $this->{self::CLASS_NAME};
    }

    /**
     * @return ?string
     */
    #[Pure] public function getBaseClassName() : ?string
    {
        $class = $this->getClassDefinition();
        return $class['name']?:null;
    }

    /**
     * @return ?string
     */
    #[Pure] public function getFullClassName() : ?string
    {
        $namespace = $this->getNameSpace();
        $baseClass = $this->getBaseClassName();
        if (!$namespace) {
            return $baseClass;
        }
        if (!$baseClass) {
            return null;
        }
        return sprintf('%s\%s', $namespace, $baseClass);
    }

    /**
     * @return bool
     */
    #[Pure] public function isFinalClass() : bool
    {
        $class = $this->getClassDefinition();
        return (bool) $class['type']['final'];
    }
    /**
     * @return bool
     */
    #[Pure] public function isAbstract() : bool
    {
        $class = $this->getClassDefinition();
        return (bool) $class['type']['name'] == 'abstract';
    }
    /**
     * @return bool
     */
    #[Pure] public function isTrait() : bool
    {
        $class = $this->getClassDefinition();
        return (bool) $class['type']['name'] == 'trait';
    }

    /**
     * @return bool
     */
    #[Pure] public function isInterface() : bool
    {
        $class = $this->getClassDefinition();
        return (bool) $class['type']['name'] == 'interface';
    }

    #[Pure] public function isChild() : bool
    {
        return $this->getClassDefinition()['isChild'] === true;
    }

    /**
     * @return bool
     */
    #[Pure] public function hasParent() : bool
    {
        return $this->getClassDefinition()['hasParent'] === true;
    }

    /**
     * @return bool
     */
    public function hasImport() : bool
    {
        return count($this->getClassDefinition()['use']) > 0;
    }

    /**
     * @return bool
     */
    public function hasDeclare() : bool
    {
        return count($this->getClassDefinition()['declare']) > 0;
    }

    /**
     * @return ?string
     */
    #[Pure] public function getParentClass() : ?string
    {
        $class = $this->getClassDefinition();
        return $class['parents']['extend']['name']??null;
    }

    /**
     * @param string|object $className
     * @return bool
     */
    #[Pure] public function isSubClassOf(string|object $className) : bool
    {
        $classNameFull = $this->getFullClassName();
        if (!$classNameFull) {
            return false;
        }

        return is_subclass_of($classNameFull, $className);
    }

    #[Pure] public function getMethods() : array
    {
        return $this->getClassDefinition()['methods']??[];
    }

    /**
     * @return bool
     */
    #[Pure] public function hasConstructor() : bool
    {
        return (bool) $this->getClassDefinition()['hasConstructor'];
    }

    /**
     * @return ?string
     */
    #[Pure] public function getConstructorName() : ?string
    {
        return $this->getClassDefinition()['constructor']??null;
    }

    /**
     * @param string $method
     * @return ?array
     */
    #[Pure] public function getMethod(string $method) : ?array
    {
        return $this->getMethods()[strtolower($method)]??null;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function get(string $name): mixed
    {
        return $this->$name;
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->definitions[$name]??null;
    }

    public function serialize(): string
    {
        return serialize($this->definitions);
    }

    public function unserialize($data)
    {
        $this->definitions = unserialize($data);
    }

    public function __unserialize(array $data): void
    {
        $this->definitions = $data;
    }
    public function __serialize(): array
    {
        return $this->definitions;
    }
}
