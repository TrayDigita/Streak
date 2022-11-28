<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

class Validator
{
    public static function isValidNamespace(string $nameSpace) : bool
    {
        return (bool) preg_match(
            '~^(\\\?[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*(\\\[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*)*\\\?|\\\)$~',
            $nameSpace
        );
    }

    public static function isValidClassName(string $className) : bool
    {
        return (bool) preg_match(
            '~^\\\?[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*(\\\[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*)*$~',
            $className
        );
    }

    public static function getNamespace(string $fullClassName) : string|false
    {
        if (!self::isValidClassName($fullClassName)) {
            return false;
        }
        return preg_replace(
            '~^\\\?(?:
                    ([A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*(?:\\\[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9]*)*)
                \\\)?
                ([A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*[A-Z-a-z_0-9\x80-\xff]*)
            $~x',
            '$1',
            $fullClassName,
        );
    }

    public static function getClassBaseName(string $fullClassName) : string|false
    {
        if (!self::isValidClassName($fullClassName)) {
            return false;
        }

        return preg_replace(
            '~
                ^\\\?(?:[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*
                (?:\\\[A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*)*\\\)?
                ([A-Z-a-z_\x80-\xff]+[A-Z-a-z_0-9\x80-\xff]*)
            $~x',
            '$1',
            $fullClassName,
        );
    }

    public static function isValidFunctionName(string $name) : bool
    {
        return (bool) preg_match('~[_a-zA-Z\x80-\xff]+[a-zA-Z0-9_\x80-\xff]*$~', $name);
    }

    public static function isValidVariableName(string $name) : bool
    {
        return (bool) preg_match('~[_a-zA-Z\x80-\xff]+[a-zA-Z0-9_\x80-\xff]*$~', $name);
    }

    public static function isCli() : bool
    {
        return in_array(PHP_SAPI, ['cli', 'phpdbg', 'embed'], true);
    }

    /**
     * @return bool
     */
    public static function isWindows() : bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    /**
     * @return bool
     */
    public static function isUnix() : bool
    {
        return DIRECTORY_SEPARATOR === '/';
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function isRelativePath(string $path) : bool
    {
        $path = preg_replace('~[\\\|/]+~', DIRECTORY_SEPARATOR, $path);
        return (bool) (self::isWindows() ? preg_match('~^[A-Za-z]+:[\\\]~', $path) : preg_match('~^/~', $path));
    }

    /**
     * @param string $str
     * @return bool
     */
    public static function isBinary(string $str) : bool
    {
        return preg_match('~[^\x20-\x7E\t\r\n]~', $str) > 0;
    }

    /**
     * @param string $str
     * @return bool
     */
    public static function isBase64(string $str) : bool
    {
        return preg_match(
            '~^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$~',
            $str
        ) > 0;
    }

    /**
     * @param string $str
     * @return bool
     */
    public static function isHttpUrl(string $str) : bool
    {
        return preg_match('~^https?://[^.]+\.(.+)$~i', $str) > 0;
    }

    /**
     * Sanitize Result to UTF-8 , this is recommended to sanitize
     * that result from socket that invalid decodes UTF8 values
     *
     * @param string $string
     *
     * @return string
     */
    public function sanitizeInvalidUTF8(string $string) : string
    {
        if (!function_exists('iconv')) {
            return $string;
        }

        if (! function_exists('mb_strlen') || mb_strlen($string, 'UTF-8') !== strlen($string)) {
            $result = Consolidation::callbackReduceError(
                fn () => iconv('windows-1250', 'UTF-8//IGNORE', $string),
                $errNo
            );
            !$errNo && $string = $result;
        }

        return $string;
    }

    /* --------------------------------------------------------------------------------*
     |                              Serialize Helper                                   |
     |                                                                                 |
     | Custom From WordPress Core wp-includes/functions.php                            |
     |---------------------------------------------------------------------------------|
     */

    /**
     * Check value to find if it was serialized.
     * If $data is not a string, then returned value will always be false.
     * Serialized data is always a string.
     *
     * @param  mixed $data   Value to check to see if was serialized.
     * @param  bool  $strict Optional. Whether to be strict about the end of the string. Defaults true.
     * @return bool  false if not serialized and true if it was.
     */
    public static function isSerialized(mixed $data, bool $strict = true): bool
    {
        /* if it isn't a string, it isn't serialized
         ------------------------------------------- */
        if (! is_string($data) || trim($data) == '') {
            return false;
        }

        $data = trim($data);
        // null && boolean
        if ('N;' == $data || $data == 'b:0;' || 'b:1;' == $data) {
            return true;
        }

        if (strlen($data) < 4 || ':' !== $data[1]) {
            return false;
        }

        if ($strict) {
            $last_char = substr($data, -1);
            if (';' !== $last_char && '}' !== $last_char) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace     = strpos($data, '}');

            // Either ; or } must exist.
            if (false === $semicolon && false === $brace
                || false !== $semicolon && $semicolon < 3
                || false !== $brace && $brace < 4
            ) {
                return false;
            }
        }

        $token = $data[0];
        switch ($token) {
            /** @noinspection PhpMissingBreakStatementInspection */
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (!str_contains($data, '"')) {
                    return false;
                }
            // or else fall through
            case 'a':
            case 'O':
            case 'C':
                return (bool) preg_match("/^$token:[0-9]+:/s", $data);
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';
                return (bool) preg_match("/^$token:[0-9.E-]+;$end/", $data);
        }

        return false;
    }

    /**
     * Un-serialize value only if it was serialized.
     *
     * @param string $original Maybe un-serialized original, if is needed.
     *
     * @return mixed  Un-serialized data can be any type.
     */
    public static function unSerialize(mixed $original): mixed
    {
        if (! is_string($original) || trim($original) == '') {
            return $original;
        }

        /**
         * Check if serialized
         * check with trim
         */
        if (self::isSerialized($original)) {
            $result = Consolidation::callbackReduceError(
                fn() => @unserialize(trim($original)),
                $errNo
            );
            !$errNo && $original = $result;
            unset($result);
        }

        return $original;
    }

    /**
     * Serialize data, if needed. @uses for ( un-compress serialize values )
     * This method to use safe as safe data on database. Value that has been
     * Serialized will be double serialize to make sure data is stored as original
     *
     *
     * @param  mixed $data            Data that might be serialized.
     * @param  bool  $doubleSerialize Double Serialize if you want to use returning real value of serialized
     *                                for database result
     * @return mixed A scalar data
     */
    public static function maybeShouldSerialize(mixed $data, bool $doubleSerialize = true): mixed
    {
        if (is_array($data) || is_object($data)) {
            return @serialize($data);
        }

        /**
         * Double serialization is required for backward compatibility.
         * if @param bool $doubleSerialize is enabled
         */
        if ($doubleSerialize && self::isSerialized($data, false)) {
            return serialize($data);
        }

        return $data;
    }

    /**
     * @param string $regexP
     *
     * @return bool
     */
    public static function isValidRegExP(string $regexP) : bool
    {
        if (@preg_match($regexP, '') === false) {
            error_clear_last();
            return false;
        }

        return true;
    }
}
