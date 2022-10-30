<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Uploads\Exceptions;

use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Throwable;

class InvalidOffsetPosition extends RuntimeException
{
    #[Pure] public function __construct(
        private int $requestPosition,
        private int $currentPosition,
        $message = "",
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return int
     */
    public function getRequestPosition(): int
    {
        return $this->requestPosition;
    }

    /**
     * @return int
     */
    public function getCurrentPosition(): int
    {
        return $this->currentPosition;
    }
}
