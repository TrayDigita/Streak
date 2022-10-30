<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Uploads\Exceptions;

use JetBrains\PhpStorm\Pure;
use RuntimeException;
use Throwable;

class DirectoryUnWritAbleException extends RuntimeException
{
    #[Pure] public function __construct(
        private string $directory,
        $message = "",
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }
}
