<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Abstracts;

use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Interfaces\ContainerizeInterface;
use TrayDigita\Streak\Source\Traits\Containerize;

abstract class AbstractContainerization implements ContainerizeInterface
{
    use Containerize;

    /**
     * @param Container $container
     */
    public function __construct(private Container $container)
    {
    }
}
