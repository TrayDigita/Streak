<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL;

use JetBrains\PhpStorm\ArrayShape;
use TrayDigita\Streak\Source\ACL\Interfaces\IdentityInterface;
use TrayDigita\Streak\Source\Traits\PasswordHashed;

/**
 * Class helper
 */
class UserControl
{
    use PasswordHashed;

    /**
     * @var array<string, IdentityInterface>
     */
    protected array $identities = [];

    /**
     * @param int|string|float $id
     * @param ?string $username
     * @param ?string $email
     * @param ?string $password
     */
    public function __construct(
        protected int|string|float $id,
        protected ?string $username = null,
        protected ?string $email = null,
        protected ?string $password = null
    ) {
    }

    /**
     * Validate password
     *
     * @param string $password
     *
     * @return bool
     */
    public function validate(string $password) : bool
    {
        $userPassword = $this->getPassword();
        if (!$userPassword) {
            return false;
        }
        return $this->verifyPassword($password, $userPassword);
    }

    /**
     * @return float|int|string
     */
    public function getId(): float|int|string
    {
        return $this->id;
    }

    /**
     * @return ?string
     */
    public function getUsername() : ?string
    {
        return $this->username;
    }

    /**
     * @return ?string
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return ?string
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param IdentityInterface $identity
     *
     * @return $this
     */
    public function addControl(IdentityInterface $identity) : static
    {
        $this->identities[$identity->getId()] = $identity;
        return $this;
    }

    /**
     * @param IdentityInterface|string $identity
     *
     * @return $this
     */
    public function removeControl(IdentityInterface|string $identity) : static
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        unset($this->identities[$identity]);
        return $this;
    }

    /**
     * @return array<string, IdentityInterface>
     */
    #[ArrayShape([
        'string' => 'array<\TrayDigita\Streak\Source\ACL\Interfaces\IdentityInterface>'
    ])] public function getIdentities(): array
    {
        return $this->identities;
    }
}
