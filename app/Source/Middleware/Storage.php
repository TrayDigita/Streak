<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Middleware;

use JetBrains\PhpStorm\Pure;
use Slim\Interfaces\MiddlewareDispatcherInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Benchmark;

class Storage extends AbstractContainerization implements Startable
{
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
        protected MiddlewareDispatcherInterface $middlewareDispatcher
    ) {
        parent::__construct($container);
    }

    /**
     * @return MiddlewareDispatcherInterface
     */
    public function getMiddlewareDispatcher(): MiddlewareDispatcherInterface
    {
        return $this->middlewareDispatcher;
    }

    /**
     * @return Collector
     */
    public function getCollectorMiddleware(): Collector
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
        $timeRecord->start('StorageMiddlewares:load');
        // events
        $events->dispatch('StorageMiddlewares:middlewares:start', $this);
        // reverse
        foreach (array_reverse($this->collectorModules->getMiddlewaresKey()) as $middlewareName) {
            $middleware = $this->collectorModules->getMiddleware($middlewareName);
            $className  = get_class($middleware);
            $classNameId = sprintf('StorageMiddlewares:load[%s]', $className);
            $timeRecord->start($classNameId);
            $this->collectorModules->load(
                $this,
                $middleware,
                $this->getMiddlewareDispatcher(),
                function ($middleware) use ($events, $className) {
                    $events->dispatch('StorageMiddlewares:middleware:prepare', $middleware, $this);
                    $this
                        ->getMiddlewareDispatcher()
                        ->addMiddleware($middleware);
                    $this->registered[$className] = true;
                    $events->dispatch('StorageMiddlewares:middleware:registered', $middleware, $this);
                }
            );
            $timeRecord->stop($classNameId);
        }
        // events
        $this->getContainer(Events::class)->dispatch(
            'StorageMiddlewares:middlewares:registered',
            $this
        );
        $timeRecord->stop('StorageMiddlewares:load');
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
