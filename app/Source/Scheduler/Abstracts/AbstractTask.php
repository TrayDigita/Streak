<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler\Abstracts;

use Doctrine\DBAL\Exception;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Stringable;
use Throwable;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Interfaces\Abilities\Startable;
use TrayDigita\Streak\Source\Models\ActionSchedulers;
use TrayDigita\Streak\Source\Models\ActionSchedulersLog;
use TrayDigita\Streak\Source\Scheduler\TaskStatus;
use TrayDigita\Streak\Source\Time;
use TrayDigita\Streak\Source\Traits\Containerize;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\LoggingMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

abstract class AbstractTask implements Startable
{
    use Containerize,
        LoggingMethods,
        EventsMethods,
        TranslationMethods;

    const MINIMUM_EXECUTION_TIME = 0.0000001;
    const MINIMUM_CRON_TIME = 5;

    /**
     * @var Container
     * @readonly
     */
    public readonly Container $container;

    /**
     * @var string
     * @readonly
     */
    public readonly string $className;

    /**
     * @var ?TaskStatus
     */
    private ?TaskStatus $status = null;

    /**
     * Interval in seconds
     *
     * @var int
     */
    protected int $interval = 0;

    /**
     * @var ?int
     */
    private ?int $runStatus = null;

    /**
     * @var bool
     */
    private bool $prevExists = false;

    /**
     * @var ?ActionSchedulers
     */
    private ?ActionSchedulers $schedulers = null;

    /**
     * @var ?float
     */
    private ?float $processedTime = null;

    /**
     * @var ?callable
     */
    private $shutdownHandler = null;

    /**
     * @param Container $container
     */
    final public function __construct(Container $container)
    {
        $this->container = $container;
        $this->className = get_class($this);
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @throws Exception
     */
    final public function start(): TaskStatus
    {
        static $registered_handler = null;
        if (!$registered_handler && Validator::isCli()) {
            $registered_handler = true;
            register_shutdown_function(function () {
                if (is_callable($this->shutdownHandler)) {
                    call_user_func($this->shutdownHandler, $this);
                }
            });
        }
        if ($this->status) {
            return $this->status;
        }

        $runStatus = $this->getRunStatus();
        if ($runStatus !== TaskStatus::PENDING) {
            $this->status = new TaskStatus(
                $this,
                $runStatus
            );
            return $this->status;
        }

        $nowTime = $this->getContainer(Time::class)->newDateTimeUTC();
        if (!$this->schedulers) {
            (new ActionSchedulers)->insert([
                'callback' => $this->className,
                'status' => ActionSchedulers::PROGRESS,
                'created_at' => $nowTime,
                'updated_at' => '1970-01-01 00:00:00',
                'processed_time' => null
            ]);
            $this->schedulers = ActionSchedulers::find(['callback' => $this->className])->fetch();
        } else {
            ActionSchedulersLog::insertFromActionScheduler($this->schedulers);
        }
        $startTime = microtime(true);
        $this->status = null;
        $doExit = true;
        $processed_time = null;
        $this->shutdownHandler = function () use(&$doExit, &$processed_time, $startTime) {
            if (!$doExit) {
                return;
            }
            if (!$this->status) {
                $this->status = TaskStatus::create(
                    $this,
                    TaskStatus::FAILURE
                );
            }
            $this->status->setStatus(TaskStatus::FAILURE);
            $this->status->setMessage(
                $this->translate('Process Exited!')
            );
            if ($startTime < self::MINIMUM_EXECUTION_TIME) {
                $processed_time = 0;
            }
            $this->processedTime = $processed_time??(microtime(true) - $startTime);
            $this->schedulers->update([
                'status' => $this->status->isProgress()
                    ? ActionSchedulers::UNKNOWN
                    : $this->status->getStatusString(),
                'last_execute' => $this->getContainer(Time::class)->newDateTimeUTC(),
                'processed_time' => $this->processedTime,
                'message' => (string) $this->status
            ]);
        };

        try {
            $args = $this->eventDispatch(
                "Scheduler:run:args:$this->className",
                [],
                $this->className
            );

            $this->schedulers->update(['last_execute' => $nowTime, 'status' => ActionSchedulers::PROGRESS]);
            // log
            $this->logDebug($this->translate('Processing task.'), ['task' => $this->getClassName()]);
            $this->status = $this->processTask($args);
            if ($this->status->getStatus() === TaskStatus::PROGRESS) {
                $this->status->setStatus(TaskStatus::SUCCESS);
            }

            $doExit = false;
            $processed_time = microtime(true) - $startTime;
            // log
            $this->logDebug(
                $this->translate('Task done.'),
                [
                    'task' => $this->getClassName(),
                    'status' => $this->status->getStatusString(),
                ]
            );
        } catch (Throwable $e) {
            $doExit = false;
            $this->logError(
                $this->translate('Task error.'),
                [
                    'exception' => $e,
                    'task' => $this->getClassName(),
                ]
            );
            $processed_time = microtime(true) - $startTime;
            $this->status = new TaskStatus(
                $this,
                TaskStatus::FAILURE,
                $e
            );
        } finally {
            $this->shutdownHandler = null;
            if ($doExit) {
                if (!$this->status) {
                    $this->status = TaskStatus::create(
                        $this,
                        TaskStatus::FAILURE
                    );
                }
                $this->status->setStatus(TaskStatus::FAILURE);
                $this->status->setMessage(
                    $this->translate('Process Exited!')
                );
            }
            if ($this->status === null) {
                $this->status = new TaskStatus(
                    $this,
                    TaskStatus::FAILURE,
                    $this->translate('Unknown')
                );
            }

            if ($this->status->getMessage() instanceof Throwable) {
                $this->status->setStatus(TaskStatus::FAILURE);
            }

            if ($startTime < self::MINIMUM_EXECUTION_TIME) {
                $processed_time = 0;
            }
            $this->processedTime = $processed_time;
            $this->schedulers->update([
                'status' => $this->status->isProgress()
                    ? ActionSchedulers::UNKNOWN
                    : $this->status->getStatusString(),
                'last_execute' => $this->getContainer(Time::class)->newDateTimeUTC(),
                'processed_time' => $this->processedTime,
                'message' => (string) $this->status
            ]);
        }

        $this->schedulers = null;
        return $this->status;
    }

    final public function isNeedToRun() : bool
    {
        try {
            return $this->getRunStatus() === TaskStatus::PENDING;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @throws Exception
     */
    final public function getRunStatus() : int
    {
        if ($this->runStatus !== null) {
            return $this->runStatus;
        }

        $interval = $this->getInterval();
        $last_execute = null;
        $nowTime   = $this->getContainer(Time::class)->newDateTimeUTC();
        $this->schedulers = ActionSchedulers::find(['callback' => $this->className])->fetch()?:null;
        $status = ActionSchedulers::PENDING;
        if ($this->schedulers) {
            $this->prevExists = true;
            $last_execute = $this->schedulers->last_execute?->getTimestamp();
            $status = $this->schedulers->status;
        }

        $current = $nowTime->getTimestamp();
        if ($interval < 1) {
            $this->runStatus = TaskStatus::SKIPPED;
            return $this->runStatus;
        }

        if (!$last_execute) {
            $this->runStatus = TaskStatus::PENDING;
            return $this->runStatus;
        }

        $fifteen_minutes = 15 * 60; // 15 minutes
        // convert to seconds
        $interval_since  = ($current - $last_execute);
        $minimumCronTime = self::MINIMUM_CRON_TIME;
        $minimumCronTime = $this->eventDispatch(
            "Runner:minimum_time:$this->className",
            $minimumCronTime,
            $interval,
            $this
        );

        $interval = $minimumCronTime > $interval ? $minimumCronTime : $interval;
        $need = $interval_since < 0 || $interval_since > $interval;
        $rangeOfTime = $this->eventDispatch(
            "Runner:force_time:$this->className",
            $fifteen_minutes,
            $interval,
            $need,
            $this
        );

        if (!is_int($rangeOfTime)) {
            $rangeOfTime = $fifteen_minutes;
        } elseif ($rangeOfTime < 60) {
            $rangeOfTime  = 60;
        }

        if ($status !== ActionSchedulers::PROGRESS) {
            $this->runStatus = $need ? TaskStatus::PENDING : TaskStatus::SKIPPED;
        } else {
            $this->runStatus = $interval_since > $rangeOfTime
                ? TaskStatus::PENDING
                : TaskStatus::PROGRESS;
        }

        return $this->runStatus;
    }

    /**
     * @return bool
     */
    final public function started(): bool
    {
        return $this->status !== null;
    }

    #[Pure] final protected function createStatus(
        int $status,
        string|Stringable|JsonSerializable $message
    ) : TaskStatus {
        return TaskStatus::create(
            $this,
            $status,
            $message
        );
    }

    /**
     * @return TaskStatus|null
     */
    public function getStatus(): ?TaskStatus
    {
        return $this->status;
    }

    /**
     * @return float|null
     */
    public function getProcessedTime(): ?float
    {
        return $this->processedTime;
    }

    /**
     * Interval in minutes
     *
     * @return int|float
     */
    public function getInterval(): int|float
    {
        return $this->interval;
    }

    final public function __clone(): void
    {
        $this->runStatus = null;
    }

    /**
     * Process task
     *
     * @param array $arguments
     *
     * @return TaskStatus
     */
    abstract protected function processTask(array $arguments = []): TaskStatus;
}
