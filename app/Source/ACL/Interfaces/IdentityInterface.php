<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL\Interfaces;

use Stringable;

interface IdentityInterface extends Stringable
{
    /**
     * Control ACL Name
     *
     * @return string
     */
    public function getName() : string;

    /**
     * Primary unique id
     *
     * @return string
     */
    public function getId() : string;

    /**
     * @param AccessInterface $access
     *
     * @return $this
     */
    public function add(AccessInterface $access) : static;

    /**
     * @param AccessInterface|string $access
     *
     * @return bool
     */
    public function has(AccessInterface|string $access) : bool;

    /**
     * @param AccessInterface|string $access
     *
     * @return $this
     */
    public function remove(AccessInterface|string $access) : static;

    /**
     * @param AccessInterface|string $access
     *
     * @return ?AccessInterface
     */
    public function get(AccessInterface|string $access) : ?AccessInterface;

    /**
     * @param AccessInterface|string $access
     *
     * @return bool
     */
    public function permit(AccessInterface|string $access) : bool;

    /**
     * @return string
     * @uses getId();
     */
    public function __toString(): string;
}
