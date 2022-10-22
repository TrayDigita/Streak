<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Middleware\Interfaces;

use Psr\Http\Server\MiddlewareInterface;
use TrayDigita\Streak\Source\Interfaces\PriorityCallableInterface;

interface CallableMiddleware extends PriorityCallableInterface, MiddlewareInterface
{
}
