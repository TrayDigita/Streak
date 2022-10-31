<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL;

use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\ACL\Interfaces\AccessInterface;
use TrayDigita\Streak\Source\ACL\Interfaces\IdentityInterface;

/**
 * Accesses only stored access directly.
 * Access inside of identities not imported
 */
class Lists extends AbstractContainerization
{
    /**
     * @var array<string, IdentityInterface>
     */
    protected array $identities = [];

    /**
     * @var array<string, AccessInterface>
     */
    protected array $accesses = [];

    /**
     * @return array<string, IdentityInterface>
     */
    public function getIdentities(): array
    {
        return $this->identities;
    }

    /**
     * @return array<string, AccessInterface>
     */
    public function getAccesses(): array
    {
        return $this->accesses;
    }

    /**
     * @param AccessInterface $access
     *
     * @return $this
     */
    public function addAccess(AccessInterface $access) : static
    {
        $this->accesses[$access->getId()] = $access;
        return $this;
    }

    /**
     * @param IdentityInterface $identity
     *
     * @return $this
     */
    public function addIdentity(IdentityInterface $identity) : static
    {
        $this->identities[$identity->getId()] = $identity;
        return $this;
    }

    /**
     * @param IdentityInterface|string $identity
     *
     * @return ?IdentityInterface
     */
    public function getIdentity(IdentityInterface|string $identity): ?IdentityInterface
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        return $this->identities[$identity]??null;
    }

    /**
     * @param AccessInterface|string $access
     *
     * @return ?AccessInterface
     */
    public function getAccess(AccessInterface|string $access): ?AccessInterface
    {
        $access = is_string($access) ? $access : $access->getId();
        return $this->accesses[$access] ?? null;
    }

    /**
     * @param IdentityInterface|string $identity
     *
     * @return $this
     */
    public function removeIdentity(IdentityInterface|string $identity) : static
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        unset($this->identities[$identity]);
        return $this;
    }

    /**
     * @param AccessInterface|string $identity
     *
     * @return $this
     */
    public function removeAccess(AccessInterface|string $identity) : static
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        unset($this->identities[$identity]);
        return $this;
    }

    /**
     * @param IdentityInterface|string $identity
     *
     * @return bool
     */
    public function hasIdentity(IdentityInterface|string $identity) : bool
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        return isset($this->identities[$identity]);
    }

    /**
     * @param AccessInterface|string $access
     *
     * @return bool
     */
    public function hasAccess(AccessInterface|string $access) : bool
    {
        $access = is_string($access) ? $access : $access->getId();
        return isset($this->accesses[$access]);
    }

    /**
     * @param AccessInterface|string $access
     * @param IdentityInterface|string $identity
     *
     * @return ?IdentityInterface
     */
    public function addPermission(
        AccessInterface|string $access,
        IdentityInterface|string $identity
    ): ?IdentityInterface {
        if (is_string($access)) {
            $access = $this->getAccess($access);
        }
        return $access ? $this->getIdentity($identity)?->add($access) : null;
    }

    /**
     * @param AccessInterface|string $access
     * @param IdentityInterface|string $identity
     *
     * @return ?IdentityInterface
     */
    public function removePermission(
        AccessInterface|string $access,
        IdentityInterface|string $identity
    ): ?IdentityInterface {
        return $this->getIdentity($identity)?->remove($access);
    }

    /**
     * @param AccessInterface|string $access
     * @param IdentityInterface|string $identity
     *
     * @return bool
     */
    public function permit(AccessInterface|string $access, IdentityInterface|string $identity): bool
    {
        $identity = $this->getIdentity($identity);
        return $identity && $identity->permit($access);
    }
}
