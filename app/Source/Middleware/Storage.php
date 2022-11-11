<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Middleware;

use JetBrains\PhpStorm\Pure;
use Slim\Interfaces\MiddlewareDispatcherInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Benchmark;
use TrayDigita\Streak\Source\Traits\EventsMethods;

class Storage extends AbstractContainerization implements Startable
{
    use EventsMethods;

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
     * @noinspection PhpUnused
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
        $timeRecord = $this->getContainer(Benchmark::class);
        $timeRecord->start('StorageMiddlewares:load');
        // events
        $this->eventDispatch('StorageMiddlewares:middlewares:start', $this);
        $obj = $this;
        // reverse
        foreach (array_reverse(
            $this
                ->collectorModules
                ->scan()
                ->getMiddlewares()
        ) as $middlewareName => $middleware) {
            $className   = get_class($middleware);
            $classNameId = sprintf('StorageMiddlewares:load[%s]', $className);
            $timeRecord->start($classNameId);
            $obj->eventDispatch('Storage:middleware:prepare', $middleware, $this);
            $this->getMiddlewareDispatcher()->addMiddleware($middleware);
            $this->registered[$middlewareName] = true;
            $obj->eventDispatch('Storage:middleware:registered', $middleware, $this);
            $timeRecord->stop($classNameId);
        }
        // events
        $obj->eventDispatch(
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
