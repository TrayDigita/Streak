<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL;

use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\ACL\Interfaces\AccessInterface;
use TrayDigita\Streak\Source\ACL\Interfaces\IdentityInterface;

class Lists extends AbstractContainerization
{
    /**
     * @var array<string, IdentityInterface>
     */
    protected array $identities = [];

    public function addIdentity(IdentityInterface $identity)
    {
        $this->identities[$identity->getId()] = $identity;
    }

    public function getIdentity(IdentityInterface|string $identity): ?IdentityInterface
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        return $this->identities[$identity]??null;
    }

    public function removeIdentity(IdentityInterface|string $identity)
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        unset($this->identities[$identity]);
    }

    public function hasIdentity(IdentityInterface|string $identity) : bool
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        return isset($this->identities[$identity]);
    }

    public function append(
        AccessInterface $access,
        IdentityInterface|string $identity
    ): ?IdentityInterface {
        return $this->getIdentity($identity)?->add($access);
    }

    public function permit(AccessInterface|string $access, IdentityInterface|string $identity): bool
    {
        $identity = $this->getIdentity($identity);
        return $identity && $identity->permit($access);
    }
}
