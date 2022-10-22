<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module\Abstracts;

use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Module\Interfaces\ModuleInterface;
use TrayDigita\Streak\Source\Traits\ComposerLoaderObject;
use TrayDigita\Streak\Source\Traits\EventsMethods;

abstract class AbstractModule extends AbstractContainerization implements ModuleInterface
{
    use ComposerLoaderObject,
        EventsMethods;

    private bool $hasInit = false;
    protected string $version = '';
    protected string $name = '';
    protected string $description = '';

    /**
     * @var bool instantiate the loader
     */
    protected bool $addAutoload = false;

    #[Pure] final public function __construct(Container $container)
    {
        parent::__construct($container);
        if (trim($this->name)) {
            $this->name = ucfirst(substr(strrchr(get_class($this), '\\'), 1));
        }
    }

    public static function thePriority(): int
    {
        return 10;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return bool
     */
    final public function isHasInit(): bool
    {
        return $this->hasInit;
    }

    final public function initModule(): static
    {
        if ($this->isHasInit()) {
            return $this;
        }
        $events = $this
            ->getContainer(Events::class);
        $this->hasInit = true;
        if ($this->addAutoload === true) {
            $ref = new ReflectionClass($this);
            $this->registerNamespace(
                dirname($ref->getFileName()),
                $ref->getNamespaceName()
            );
        }

        $this->eventDispatch('Module:init:before', $this);
        $this->afterInit();
        $this->eventDispatch('Module:init:after', $this);
        return $this;
    }

    abstract protected function afterInit();
}
