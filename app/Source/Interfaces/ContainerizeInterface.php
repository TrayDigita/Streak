<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces;

use TrayDigita\Streak\Source\Container;

interface ContainerizeInterface
{
    /**
     * @param ?string|class-string<T> $name
     *
     * @template T
     * @return Container|T|mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function getContainer(?string $name = null);
}
