<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use JetBrains\PhpStorm\Pure;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Records\Collections;

class Hash extends AbstractContainerization
{
    /**
     * The Salt Key
     *
     * @var string
     */
    public readonly string $saltKey;

    /**
     * The Secret Key
     *
     * @var string
     */
    public readonly string $secretKey;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        parent::__construct($container);
        $config = $container->get(Configurations::class);
        $security = $config->get('security');
        $security = $security instanceof Collections ? $security : new Configurations();

        $salt = $security->get('salt');
        $secret = $security->get('secret');

        // default salt is $_SERVER['SCRIPT_FILENAME']??__FILE__
        $default_sha1 = md5($_SERVER['SCRIPT_FILENAME']??__FILE__);
        $key          = md5($default_sha1);
        // fallback default
        $salt   = !is_string($salt) || trim($salt) === ''
            ? $default_sha1
            : $salt;
        $secret = !is_string($secret) || trim($secret) === ''
            ? sha1($salt)
            : $secret;
        $this->saltKey   = hash_hmac('sha1', $salt, $key);
        $this->secretKey = hash_hmac('sha1', $secret, $key);
    }

    /**
     * @return string
     */
    public function getSaltKey(): string
    {
        return $this->saltKey;
    }

    /**
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * Triple Hmac
     *
     * @param string $algo
     * @param string $data
     * @param string $key
     * @param bool $binary
     *
     * @return string
     */
    #[Pure] public function hmac(string $algo, string $data, string $key, bool $binary = false): string
    {
        return hash_hmac(
            $algo,
            $this->hash($algo, $data, true),
            $key,
            $binary
        );
    }

    /**
     * @uses hash_hmac() twice ->saltKey > ->secretKey
     *
     * @param string $algo
     * @param string $data
     * @param bool $binary
     *
     * @return string
     */
    public function hash(string $algo, string $data, bool $binary = false) : string
    {
        return hash_hmac(
            $algo,
            hash_hmac(
                $algo,
                $data,
                $this->saltKey,
                true
            ),
            $this->secretKey,
            $binary
        );
    }

    #[Pure] public function md5(string $data, bool $binary = false) : string
    {
        return $this->hash('md5', $data, $binary);
    }

    #[Pure] public function sha1(string $data, bool $binary = false) : string
    {
        return $this->hash('sha1', $data, $binary);
    }

    #[Pure] public function sha256(string $data, bool $binary = false) : string
    {
        return $this->hash('sha256', $data, $binary);
    }

    #[Pure] public function sha512(string $data, bool $binary = false) : string
    {
        return $this->hash('sha512', $data, $binary);
    }
}
