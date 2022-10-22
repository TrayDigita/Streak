<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Generator;

use Stringable;
use Throwable;

class RandomString implements Stringable
{
    /**
     * @param int $length
     * @param string|null $char
     * @return string
     */
    public static function char(int $length = 64, ?string $char = null) : string
    {
        if ($length < 1) {
            return '';
        }

        $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chars .='~`! @#$%^&*()_-+={[}]|\:;"\'<,>.?/';
        $chars = $char?:$chars;
        $charactersLength = strlen($chars);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $chars[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @param int $bytes
     * @return string
     */
    public static function bytes(int $bytes) : string
    {
        static $pseudo = null;

        if ($bytes < 1) {
            return '';
        }
        try {
            return random_bytes($bytes);
        } catch (Throwable) {
            if (!is_bool($pseudo)) {
                $pseudo = function_exists('openssl_random_pseudo_bytes');
            }
            try {
                if ($pseudo) {
                    return openssl_random_pseudo_bytes($bytes);
                }
            } catch (Throwable) {
                // pass
            }
            $random = '';
            while (strlen($random) < $bytes) {
                $random .= chr(mt_rand(0, 255));
            }
            return $random;
        }
    }

    public static function createUniqueHash() : string
    {
        return md5(UUID::v4());
    }

    public function __toString() : string
    {
        return self::char();
    }
}
