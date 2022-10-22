<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Exceptions;

use JetBrains\PhpStorm\Pure;
use Throwable;

class ContainerNotFoundException extends ContainerException
{
    #[Pure] public function __construct(string $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
