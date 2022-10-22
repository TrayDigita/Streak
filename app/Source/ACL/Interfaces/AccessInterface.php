<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL\Interfaces;

use Stringable;

interface AccessInterface extends Stringable
{
    /**
     * Unique access identity
     *
     * @return string
     */
    public function getId() : string;

    /**
     * @return string
     */
    public function getName() : string;

    /**
     * Access description
     *
     * @return string
     */
    public function getDescription() : string;

    /**
     * @return string
     * @uses getId();
     */
    public function __toString(): string;
}
