<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module\Abstracts;

use JetBrains\PhpStorm\Pure;
use ReflectionClass;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Logger;
use TrayDigita\Streak\Source\Module\Interfaces\ModuleInterface;
use TrayDigita\Streak\Source\Traits\ComposerLoaderObject;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

abstract class AbstractModule extends AbstractContainerization implements ModuleInterface
{
    use ComposerLoaderObject,
        TranslationMethods,
        EventsMethods;

    /**
     * Determine the module has been init
     * @var bool
     */
    private bool $hasInit = false;

    /**
     * Module version
     * @var string
     */
    protected string $version = '';

    /**
     * Module name
     * @var string
     */
    protected string $name = '';

    /**
     * Module description
     * @var string
     */
    protected string $description = '';

    /**
     * Instantiate the loader
     * @var bool
     */
    protected bool $addAutoload = false;

    /**
     * Set module direct initialize after load
     * @var bool
     */
    protected bool $initializeModule = true;

    #[Pure] final public function __construct(Container $container)
    {
        parent::__construct($container);
        if (trim($this->name) === '') {
            $this->name = ucfirst(substr(strrchr(get_class($this), '\\'), 1));
        }
    }

    /**
     * Module priority
     *
     * @return int
     */
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
    final public function isInitializeModule(): bool
    {
        return $this->eventDispatch(
            'Module:initializeModule',
            $this->initializeModule,
            $this
        );
    }

    /**
     * @return bool
     */
    final public function isHasInit(): bool
    {
        return $this->hasInit;
    }

    /**
     * Processing module init
     *
     * @return $this
     */
    final public function initModule(): static
    {
        if ($this->isHasInit()) {
            return $this;
        }

        $this->hasInit = true;
        $className = get_class($this);
        $this->getContainer(Logger::class)->debug(
            sprintf(
                $this->translate('Module %s initialized.'),
                $className
            ),
            [
                'module' => $className
            ]
        );

        $this->eventDispatch('Module:init:before', $this);
        if ($this->addAutoload === true) {
            $ref = new ReflectionClass($this);
            $this->registerNamespace(
                dirname($ref->getFileName()),
                $ref->getNamespaceName()
            );
        }
        $this->afterInit();
        $this->eventDispatch('Module:init:after', $this);
        return $this;
    }

    /**
     * Doing the process after module init
     * @see initModule()
     */
    abstract protected function afterInit();
}
