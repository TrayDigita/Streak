<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler\Exceptions;

use JetBrains\PhpStorm\Pure;
use Throwable;

class TaskInProgress extends TaskException
{
    private string $taskName;

    #[Pure] public function __construct(
        string $taskName,
        ?string $message = '',
        $code = 0,
        Throwable $previous = null
    ) {
        $this->taskName = $taskName;
        $message = $message?:sprintf('%s already in progress.', $taskName);
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getTaskName(): string
    {
        return $this->taskName;
    }
}
