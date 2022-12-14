<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use TrayDigita\Streak\Source\Container;

trait Containerize
{
    /**
     * @param ?string|class-string<T> $name
     * @template T
     * @return Container|T|mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    final public function getContainer(?string $name = null)
    {
        $container = $this->container??Container::getInstance();
        return $name ? $container[$name] : $container;
    }
}
