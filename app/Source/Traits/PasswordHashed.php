<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use const PASSWORD_BCRYPT;

trait PasswordHashed
{
    public function hashPassword(string $password, int $cost = 10) : string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    public function verifyPassword(string $plainText, string $storedPassword) : bool
    {
        return password_verify($plainText, $storedPassword);
    }

    public function passwordNeedRehash(string $storedPassword) : bool
    {
        return password_needs_rehash($storedPassword, PASSWORD_BCRYPT);
    }
}
