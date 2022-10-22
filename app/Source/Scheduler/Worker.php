<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler;

use Doctrine\DBAL\Exception;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Scheduler\Abstracts\AbstractTask;

final class Worker implements Startable
{
    private bool $started = false;

    public function __construct(private AbstractTask $task, private Scheduler $tasks)
    {
    }

    /**
     * @return AbstractTask
     */
    public function getTask(): AbstractTask
    {
        return $this->task;
    }

    /**
     * @return Scheduler
     */
    public function getTasks(): Scheduler
    {
        return $this->tasks;
    }

    /**
     * @throws Exception
     */
    public function start(): AbstractTask
    {
        if ($this->started) {
            return $this->getTask();
        }

        $this->started = true;
        $this->getTask()->start();
        return $this->task;
    }

    public function started(): bool
    {
        return $this->started;
    }
}
