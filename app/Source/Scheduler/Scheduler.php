<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler;

use Doctrine\DBAL\Exception;
use ReflectionClass;
use ReflectionException;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Scheduler\Abstracts\AbstractTask;
use TrayDigita\Streak\Source\Scheduler\Exceptions\TaskAlreadyRun;
use TrayDigita\Streak\Source\Scheduler\Exceptions\TaskException;
use TrayDigita\Streak\Source\Scheduler\Exceptions\TaskInProgress;
use TrayDigita\Streak\Source\Models\ActionSchedulers;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\LoggingMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

final class Scheduler extends AbstractContainerization implements Startable
{
    use TranslationMethods,
        EventsMethods,
        LoggingMethods;

    /**
     * @var array<string, AbstractTask>
     */
    protected array $pending = [];

    /**
     * @var array<string, AbstractTask>
     */
    protected array $processed = [];

    /**
     * @var array<string, AbstractTask>
     */
    protected array $skipped = [];

    /**
     * @var array<string, AbstractTask>
     */
    protected array $progress = [];

    /**
     * @var array
     */
    protected array $lists = [];

    /**
     * @var bool
     */
    private bool $started = false;

    /**
     * @var array[]
     */
    private array $processRecords = [
        ActionSchedulers::PENDING => [],
        ActionSchedulers::SUCCESS => [],
        ActionSchedulers::FAILURE => [],
        ActionSchedulers::UNKNOWN => [],
    ];

    /**
     * @throws ReflectionException
     */
    public function register(AbstractTask|string $task): self
    {
        if (is_string($task)) {
            $ref = new ReflectionClass($task);
            if (!$ref->isSubclassOf(AbstractTask::class)) {
                throw new TaskException(
                    sprintf(
                        $this->translate(
                            'Task must be subclass of %s, %s given.'
                        ),
                        AbstractTask::class,
                        $ref->getName()
                    )
                );
            }
            $taskName = $ref->getName();
            $task = $taskName;
        } else {
            $taskName = get_class($task);
        }
        $lowerName = strtolower($taskName);
        if (isset($this->processed[$lowerName])) {
            throw new TaskAlreadyRun(
                $taskName,
                sprintf(
                    $this->translate('Can not add task, task %s already processed.'),
                    $taskName
                )
            );
        }
        if (isset($this->progress[$lowerName])) {
            throw new TaskInProgress(
                $taskName,
                sprintf(
                    $this->translate('Can not add task, task %s in progress.'),
                    $taskName
                )
            );
        }
        $this->lists[$lowerName] = $taskName;
        $this->pending[] = is_string($task) ? new $task($this->getContainer()) : $task;
        return $this;
    }

    public function unregister(AbstractTask|string $task): self
    {
        $taskName = is_string($task) ? $task : get_class($task);
        $taskName = strtolower($taskName);
        if (isset($this->progress[$taskName])) {
            throw new TaskInProgress(
                $taskName,
                sprintf(
                    $this->translate('Can not delete task, task %s in progress.'),
                    $taskName
                )
            );
        }
        unset($this->pending[$taskName], $this->lists[$taskName]);
        return $this;
    }

    public function has(AbstractTask|string $task) : bool
    {
        $taskName = is_string($task) ? $task : get_class($task);
        $taskName = strtolower($taskName);
        return isset($this->lists[$taskName]);
    }

    /**
     * @return array<string, string>
     */
    public function getLists(): array
    {
        return $this->lists;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function start()
    {
        $this->started = true;
        $this->eventDispatch("Scheduler:beforeRun", $this);
        foreach ($this->pending as $key => $task) {
            // remove pending
            unset($this->pending[$key]);
            // move to progress
            $this->progress[$key] = $task;
            if ($task->isNeedToRun()) {
                $name = $task->getClassName();
                $worker  = new Worker($task, $this);
                // events
                $lastEvent = $this->eventDispatch("Scheduler:before:run", $name, $task, $this);

                try {
                    $task = $worker->start();
                } catch (Exception) {
                }

                $keyName = $task ? match ($task->getStatus()?->getStatus()) {
                    TaskStatus::SKIPPED => ActionSchedulers::SKIPPED,
                    TaskStatus::FAILURE => ActionSchedulers::FAILURE,
                    TaskStatus::PENDING => ActionSchedulers::PENDING,
                    TaskStatus::SUCCESS => ActionSchedulers::SUCCESS,
                    default => ActionSchedulers::UNKNOWN
                } : ActionSchedulers::UNKNOWN;

                $this->processRecords[$keyName][$key] = $task->getProcessedTime();
                unset($this->progress[$key]);
                $this->processed[$key] = $task;

                $this->eventDispatch("Scheduler:after:run", $task, $lastEvent, $name, $this);
            } else {
                // remove progress
                unset($this->progress[$key]);
                $this->skipped[$key] = $task;
            }
        }

        $this->eventDispatch("Scheduler:afterRun", $this);
    }

    /**
     * @return AbstractTask[]
     */
    public function getPending(): array
    {
        return $this->pending;
    }

    /**
     * @return AbstractTask[]
     */
    public function getProcessed(): array
    {
        return $this->processed;
    }

    /**
     * @return AbstractTask[]
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    /**
     * @return AbstractTask[]
     */
    public function getProgress(): array
    {
        return $this->progress;
    }
}
