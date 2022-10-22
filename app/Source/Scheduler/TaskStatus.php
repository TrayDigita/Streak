<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler;

use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Stringable;
use TrayDigita\Streak\Source\Scheduler\Abstracts\AbstractTask;
use TrayDigita\Streak\Source\Scheduler\Model\ActionSchedulers;

final class TaskStatus
{
    const PENDING = -1;
    const SUCCESS = 0;
    const FAILURE = 1;
    const SKIPPED = 3;
    const PROGRESS = 4;

    private AbstractTask $task;
    private int $status;
    private string|Stringable|JsonSerializable $message;

    public function __construct(
        AbstractTask $abstractTask,
        int $status,
        string|Stringable|JsonSerializable $message = ''
    ) {
        $this->task = $abstractTask;
        $this->status = $status;
        $this->message = $message;
    }

    /**
     * @param AbstractTask $task
     * @param int $status
     * @param string|Stringable|JsonSerializable $message
     *
     * @return TaskStatus
     */
    #[Pure] public static function create(
        AbstractTask $task,
        int $status,
        string|Stringable|JsonSerializable $message = ''
    ) : TaskStatus {
        return new self($task, $status, $message);
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    #[Pure] public function getStatusString() : string
    {
        return match ($this->getStatus()) {
            TaskStatus::SUCCESS => ActionSchedulers::SUCCESS,
            TaskStatus::FAILURE => ActionSchedulers::FAILURE,
            TaskStatus::PENDING => ActionSchedulers::PENDING,
            TaskStatus::SKIPPED => ActionSchedulers::SKIPPED,
            default => ActionSchedulers::UNKNOWN
        };
    }

    /**
     * @param int $status
     */
    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return JsonSerializable|string|Stringable
     */
    public function getMessage(): Stringable|string|JsonSerializable
    {
        return $this->message;
    }

    /**
     * @param JsonSerializable|string|Stringable $message
     */
    public function setMessage(Stringable|string|JsonSerializable $message): void
    {
        $this->message = $message;
    }

    /**
     * @return AbstractTask
     */
    public function getTask(): AbstractTask
    {
        return $this->task;
    }
    public function isSuccess() : bool
    {
        return $this->status === self::SUCCESS;
    }

    public function isFail() : bool
    {
        return $this->status === self::FAILURE;
    }
    public function isPending() : bool
    {
        return $this->status === self::PENDING;
    }

    public function isSkipper() : bool
    {
        return $this->status === self::SKIPPED;
    }

    public function isUnknown() : bool
    {
        return !in_array($this->status, [self::SKIPPED, self::SUCCESS, self::PENDING, self::FAILURE], true);
    }

    public function __toString(): string
    {
        $message = $this->getMessage();
        if ($message instanceof JsonSerializable) {
            $message = json_encode($message);
        }
        return (string) $message;
    }
}
