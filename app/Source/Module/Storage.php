<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module;

use JetBrains\PhpStorm\Pure;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Benchmark;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\NamespacesArray;

class Storage extends AbstractContainerization implements Startable
{
    use NamespacesArray,
        EventsMethods;

    /**
     * @var bool
     */
    private bool $started = false;

    /**
     * @var array<string, bool>
     */
    protected array $registered = [];

    #[Pure] public function __construct(
        Container $container,
        protected Collector $collectorModules,
    ) {
        parent::__construct($container);
    }

    /**
     * @return Collector
     * @noinspection PhpUnused
     */
    public function getCollectorModules(): Collector
    {
        return $this->collectorModules;
    }

    public function start()
    {
        if ($this->started) {
            return;
        }
        $this->started = true;
        $timeRecord = $this->getContainer(Benchmark::class);
        $timeRecord->start('StorageModules:load');
        // events
        $this->eventDispatch('StorageModules:modules:prepare', $this);
        // reverse
        foreach ($this->collectorModules->scan()->getModules() as $moduleName => $module) {
            $moduleClass = get_class($module);
            $moduleNameId = sprintf('StorageModules:load[%s]', $moduleClass);
            $timeRecord->start($moduleNameId);
            if ($module->isInitializeModule()) {
                $this->eventDispatch('Storage:module:prepare', $module, $this);
                $module->initModule();
                $this->eventDispatch('Storage:module:registered', $module, $this);
            }
            $this->registered[$moduleName] = true;
            $timeRecord->stop($moduleNameId);
        }
        // events
        $this->eventDispatch('StorageModules:modules:registered', $this);
        $timeRecord->stop('StorageModules:load');
    }

    /**
     * @return bool
     */
    public function started(): bool
    {
        return $this->started;
    }

    /**
     * @return array
     */
    public function getRegistered(): array
    {
        return $this->registered;
    }
}
