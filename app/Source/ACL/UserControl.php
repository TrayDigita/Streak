<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL;

use TrayDigita\Streak\Source\ACL\Interfaces\IdentityInterface;
use TrayDigita\Streak\Source\Traits\PasswordHashed;

class UserControl
{
    use PasswordHashed;

    /**
     * @var array<string, IdentityInterface>
     */
    protected array $acl = [];

    /**
     * @param int $id
     * @param string $username
     * @param string $email
     * @param string $password
     */
    public function __construct(
        protected int $id,
        protected string $username,
        protected string $email,
        protected string $password
    ) {
    }

    public function validate(string $password) : bool
    {
        return self::verifyPassword($password, $this->getPassword());
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function addControl(IdentityInterface $identity) : static
    {
        $this->acl[$identity->getId()] = $identity;
        return $this;
    }

    public function removeControl(IdentityInterface|string $identity) : static
    {
        $identity = is_string($identity) ? $identity : $identity->getId();
        unset($this->acl[$identity]);
        return $this;
    }

    /**
     * @return array<string, IdentityInterface>
     */
    public function getAcl(): array
    {
        return $this->acl;
    }
}
