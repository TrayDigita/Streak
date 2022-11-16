<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util\Collector;

class Method
{
    public readonly string $name;
    public readonly string $visibility;
    public readonly bool $isPublic;
    public readonly bool $isPrivate;
    public readonly bool $isProtected;
    public readonly bool $isMagicMethod;
    public readonly bool $isAbstract;
    public readonly bool $isFinal;
    public readonly bool $isStatic;
    public readonly bool $hasReturnType;
    public readonly array $returnType;
    public readonly array $parameters;

    /**
     * @param string $name
     * @param string $visibility
     * @param bool $isPublic
     * @param bool $isPrivate
     * @param bool $isProtected
     * @param bool $isMagicMethod
     * @param bool $isAbstract
     * @param bool $isFinal
     * @param bool $isStatic
     * @param bool $hasReturnType
     * @param array $returnType
     * @param array $parameters
     */
    public function __construct(
        string $name,
        string $visibility,
        bool $isPublic,
        bool $isPrivate,
        bool $isProtected,
        bool $isMagicMethod,
        bool $isAbstract,
        bool $isFinal,
        bool $isStatic,
        bool $hasReturnType,
        array $returnType,
        array $parameters
    ) {
        $this->name = $name;
        $this->visibility = $visibility;
        $this->isPublic = $isPublic;
        $this->isPrivate = $isPrivate;
        $this->isProtected = $isProtected;
        $this->isMagicMethod = $isMagicMethod;
        $this->isAbstract = $isAbstract;
        $this->isFinal = $isFinal;
        $this->isStatic = $isStatic;
        $this->hasReturnType = $hasReturnType;
        $this->returnType = $returnType;
        $this->parameters = $parameters;
    }
}
