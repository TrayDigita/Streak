<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module;

use JetBrains\PhpStorm\Pure;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Benchmark;
use TrayDigita\Streak\Source\Module\Abstracts\AbstractModule;
use TrayDigita\Streak\Source\Traits\NamespacesArray;

class Storage extends AbstractContainerization implements Startable
{
    use NamespacesArray;

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
        $events = $this->getContainer(Events::class);
        $timeRecord = $this->getContainer(Benchmark::class);
        $timeRecord->start('StorageModules:load');
        // events
        $events->dispatch('StorageModules:modules:start', $this);
        // reverse
        foreach ($this->collectorModules->getModulesKey() as $moduleName) {
            $module = $this->collectorModules->getModule($moduleName);
            $moduleClass = get_class($module);
            $moduleNameId = sprintf('StorageModules:load[%s]', $moduleClass);
            $timeRecord->start($moduleNameId);
            $this->collectorModules->load(
                $this,
                $module,
                $moduleClass,
                function (AbstractModule $module, $moduleName) use ($events) {
                    $events->dispatch('StorageModules:module:prepare', $module, $this);
                    $module->initModule();
                    $this->registered[$moduleName] = true;
                    $events->dispatch('StorageModules:module:registered', $module, $this);
                }
            );
            $timeRecord->stop($moduleNameId);
        }

        // events
        $events->dispatch('StorageModules:modules:registered', $this);
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
