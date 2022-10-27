<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

final class Normalizer
{
    private static array $conversionTables = [
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Æ' => 'AE',
        'Ç' => 'C',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ð' => 'D',
        'Ñ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        '×' => 'x',
        'Ø' => '0',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
        'Þ' => 'b',
        'ß' => 'B',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'ae',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ð' => 'o',
        'ñ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        '÷' => '+',
        'ø' => 'o',
        'ù' => 'i',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ý' => 'y',
        'þ' => 'B',
        'ÿ' => 'y',
    ];

    /**
     * @return array
     */
    public static function getConversionTables(): array
    {
        return self::$conversionTables;
    }

    /**
     * @param mixed $value
     * @param callable $callback
     * @return mixed
     */
    public static function mapDeep(mixed $value, callable $callback): mixed
    {
        if (is_array($value)) {
            foreach ($value as $index => $item) {
                $value[$index] = self::mapDeep($item, $callback);
            }
        } elseif (is_object($value)) {
            $object_vars = get_object_vars($value);
            foreach ($object_vars as $property_name => $property_value) {
                $value->$property_name = self::mapDeep($property_value, $callback);
            }
        } else {
            $value = call_user_func($callback, $value);
        }

        return $value;
    }

    /**
     * @param string $string
     * @param $array
     */
    public static function parseStr(string $string, &$array)
    {
        parse_str($string, $array);
    }

    /**
     * @param mixed $data
     * @param string|null $prefix
     * @param string|null $sep
     * @param string $key
     * @param bool $urlEncode
     * @return string
     */
    public static function buildQuery(
        mixed $data,
        string $prefix = null,
        string $sep = null,
        string $key = '',
        bool $urlEncode = true
    ): string {
        $ret = [];

        foreach ((array)$data as $k => $v) {
            if ($urlEncode) {
                $k = urlencode($k);
            }
            if (is_int($k) && null != $prefix) {
                $k = $prefix . $k;
            }
            if (!empty($key)) {
                $k = $key . '%5B' . $k . '%5D';
            }
            if (null === $v) {
                continue;
            } elseif (false === $v) {
                $v = '0';
            }

            if (is_array($v) || is_object($v)) {
                array_push($ret, self::buildQuery($v, '', $sep, $k, $urlEncode));
            } elseif ($urlEncode) {
                array_push($ret, $k . '=' . urlencode($v));
            } else {
                array_push($ret, $k . '=' . $v);
            }
        }

        if (null === $sep) {
            $sep = ini_get('arg_separator.output');
        }

        return implode($sep, $ret);
    }

    /**
     * @param mixed ...$args
     * @return string
     */
    public static function addQueryArgs(...$args): string
    {
        if (!isset($args[0])) {
            return '';
        }
        // $uri_ = $args[0];
        if (is_array($args[0])) {
            if (count($args) < 2 || false === $args[1]) {
                $uri = $_SERVER['REQUEST_URI'];
            } else {
                $uri = $args[1];
            }
        } else {
            if (count($args) < 3 || false === $args[2]) {
                $uri = $_SERVER['REQUEST_URI'];
                if (is_string($args[0]) && preg_match('#https?://#i', $args[0])) {
                    $uri = $args[0];
                    unset($args[0]);
                    $args = array_values($args);
                } elseif (is_string($args[1]) && preg_match('#https?://#i', $args[1])) {
                    $uri = $args[1];
                    unset($args[1]);
                    $args = array_values($args);
                }
            } else {
                $uri = $args[2];
            }
        }

        $frag = strstr($uri, '#');
        if ($frag) {
            $uri = substr($uri, 0, -strlen($frag));
        } else {
            $frag = '';
        }

        if (0 === stripos($uri, 'http://')) {
            $protocol = 'http://';
            $uri = substr($uri, 7);
        } elseif (0 === stripos($uri, 'https://')) {
            $protocol = 'https://';
            $uri = substr($uri, 8);
        } else {
            $protocol = '';
        }

        if (str_contains($uri, '?')) {
            list($base, $query) = explode('?', $uri, 2);
            $base .= '?';
        } elseif ($protocol || ! str_contains($uri, '=')) {
            $base = $uri . '?';
            $query = '';
        } else {
            $base = '';
            $query = $uri;
        }

        self::parseStr($query, $qs);

        $qs = self::mapDeep($qs, 'urldecode');
        // $qs = self::mapDeep($qs, 'urlencode');
        if (is_array($args[0])) {
            foreach ($args[0] as $k => $v) {
                $qs[$k] = $v;
            }
        } elseif (isset($args[1])) {
            $qs[$args[0]] = $args[1];
        }

        foreach ($qs as $k => $v) {
            if (false === $v) {
                unset($qs[$k]);
            }
        }

        $ret = self::buildQuery($qs);
        $ret = trim($ret, '?');
        $ret = preg_replace('#=(&|$)#', '$1', $ret);
        $ret = $protocol . $base . $ret . $frag;
        return rtrim($ret, '?');
    }

    /**
     * Removes an item or items from a query string.
     *
     * @param array|string $key Query key or keys to remove.
     * @param bool|string $query Optional. When false uses the current URL. Default false.
     *
     * @return string|bool New URL query string.
     */
    public static function removeQueryArg(array|string $key, bool|string $query = false): bool|string
    {
        if (is_array($key)) { // Removing multiple keys.
            foreach ($key as $k) {
                $query = self::addQueryArgs($k, false, $query);
            }

            return $query;
        }
        return self::addQueryArgs($key, false, $query);
    }

    /**
     * @param string $string
     * @return string
     */
    public static function normalizeFileName(
        string $string
    ): string {
        $contains = false;
        $string = preg_replace_callback('~[\xc0-\xff]+~', function ($match) use (&$contains) {
            $contains = true;
            return utf8_encode($match[0]);
        }, $string);
        $string = str_replace("\t", " ", $string);
        // replace whitespace except space to empty character
        $string = preg_replace('~\x0-\x31~', '', $string);
        if ($contains) {
            // normalize ascii extended to ascii utf8
            $string = str_replace(
                array_keys(self::$conversionTables),
                array_values(self::$conversionTables),
                $string
            );
        }

        return preg_replace(
            '~[^0-9A-Za-z\-_()@\~\x32.]~',
            '-',
            $string
        );
    }

    /**
     * @param string $class
     * @param string $fallback
     * @return null|string|string[]
     */
    public static function normalizeHtmlClass(
        string $class,
        string $fallback = ''
    ): array|string|null {
        $sanitized = trim($class);
        if ($class) {
            $sanitized = preg_replace('|%[a-fA-F0-9][a-fA-F0-9]|', '', $class);
            //Limit to A-Z,a-z,0-9,_,-
            $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $sanitized);
        }

        if ('' === $sanitized && $fallback !== '') {
            return self::normalizeHtmlClass($fallback);
        }

        return $sanitized;
    }

    /**
     * @param string $data
     * @return string
     */
    public static function removeJSContent(string $data): string
    {
        return preg_replace(
            '/<(script)[^>]+?>.*?</\1>/smi',
            '',
            $data
        );
    }

    /**
     * Balances tags of string using a modified stack.
     *
     * @param string $text Text to be balanced.
     * @return string Balanced text.
     *
     * Custom mods to be fixed to handle by system result output
     * @copyright November 4, 2001
     * @version 1.1
     *
     * Modified by Scott Reilly (coffee2code) 02 Aug 2004
     *      1.1  Fixed handling of append/stack pop order of end text
     *           Added Cleaning Hooks
     *      1.0  First Version
     *
     * @author Leonard Lin <leonard@acm.org>
     * @license GPL
     */
    public static function forceBalanceTags(string $text): string
    {
        $tagStack = [];
        $stackSize = 0;
        $tagQueue = '';
        $newText = '';
        // Known single-entity/self-closing tags
        $single_tags = [
            'area',
            'base',
            'basefont',
            'br',
            'col',
            'command',
            'embed',
            'frame',
            'hr',
            'img',
            'input',
            'isindex',
            'link',
            'meta',
            'param',
            'source'
        ];
        $single_tags_2 = [
            'img',
            'meta',
            'link',
            'input'
        ];
        // Tags that can be immediately nested within themselves
        $nestable_tags = ['blockquote', 'div', 'object', 'q', 'span'];
        // check if contains <html> tag and split it
        // fix doctype
        $text = preg_replace('/<(\s+)?!(\s+)?(DOCTYPE)/i', '<!$3', $text);
        $rand = sprintf('%1$s_%2$s_%1$s', '%', mt_rand(10000, 50000));
        $randQuote = preg_quote($rand, '~');
        $text = str_replace('<!', '< ' . $rand, $text);
        // bug fix for comments - in case you REALLY meant to type '< !--'
        $text = str_replace('< !--', '<    !--', $text);
        // bug fix for LOVE <3 (and other situations with '<' before a number)
        $text = preg_replace('#<([0-9])#', '&lt;$1', $text);
        while (preg_match(
            "~<((?!\s+".$randQuote.")/?[\w:]*)\s*([^>]*)>~",
            $text,
            $regex
        )) {
            $newText .= $tagQueue;
            $i = strpos($text, $regex[0]);
            $l = strlen($regex[0]);
            // clear the shifter
            $tagQueue = '';
            // Pop or Push
            if (isset($regex[1][0]) && '/' == $regex[1][0]) { // End Tag
                $tag = strtolower(substr($regex[1], 1));
                // if too many closing tags
                if ($stackSize <= 0) {
                    $tag = '';
                    // or close to be safe $tag = '/' . $tag;
                } elseif ($tagStack[$stackSize - 1] == $tag) {
                    // if stack top value = tag close value then pop
                    // found closing tag
                    $tag = '</' . $tag . '>'; // Close Tag
                    // Pop
                    array_pop($tagStack);
                    $stackSize--;
                } else { // closing tag not at top, search for it
                    for ($j = $stackSize - 1; $j >= 0; $j--) {
                        if ($tagStack[$j] == $tag) {
                            // add tag to tag queue
                            for ($k = $stackSize - 1; $k >= $j; $k--) {
                                $tagQueue .= '</' . array_pop($tagStack) . '>';
                                $stackSize--;
                            }
                            break;
                        }
                    }
                    $tag = '';
                }
            } else { // Begin Tag
                $tag = strtolower($regex[1]);
                // Tag Cleaning
                // If it's an empty tag "< >", do nothing
                if ('' == $tag
                    // ElseIf it's a known single-entity tag, but it doesn't close itself, do so
                    // $regex[2] .= '';
                    || in_array($tag, $single_tags_2)
                ) {
                    // do nothing
                } elseif (str_ends_with($regex[2], '/')) {
                    // ElseIf it presents itself as a self-closing tag...
                    // ----
                    // ...but it isn't a known single-entity self-closing tag,
                    // then don't let it be treated as such and
                    // immediately close it with a closing tag (the tag will encapsulate no text as a result)
                    if (!in_array($tag, $single_tags)) {
                        $regex[2] = trim(substr($regex[2], 0, -1)) . "></$tag";
                    }
                } elseif (in_array($tag, $single_tags)) {
                    // ElseIf it's a known single-entity tag, but it doesn't close itself, do so
                    $regex[2] .= '/';
                } else {
                    // Else it's not a single-entity tag
                    // ---------
                    // If the top of the stack is the same as the tag we want to push, close previous tag
                    if ($stackSize > 0 && !in_array($tag, $nestable_tags)
                        && $tagStack[$stackSize - 1] == $tag
                    ) {
                        $tagQueue = '</' . array_pop($tagStack) . '>';
                        /** @noinspection PhpUnusedLocalVariableInspection */
                        $stackSize--;
                    }
                    $stackSize = array_push($tagStack, $tag);
                }
                // Attributes
                $attributes = $regex[2];
                if (!empty($attributes) && $attributes[0] != '>') {
                    $attributes = ' ' . $attributes;
                }
                $tag = '<' . $tag . $attributes . '>';
                //If already queuing a close tag, then put this tag on, too
                if (!empty($tagQueue)) {
                    $tagQueue .= $tag;
                    $tag = '';
                }
            }
            $newText .= substr($text, 0, $i) . $tag;
            $text = substr($text, $i + $l);
        }
        // Clear Tag Queue
        $newText .= $tagQueue;
        // Add Remaining text
        $newText .= $text;
        unset($text); // freed memory
        // Empty Stack
        while ($x = array_pop($tagStack)) {
            $newText .= '</' . $x . '>'; // Add remaining tags to close
        }
        // fix for the bug with HTML comments
        $newText = str_replace("< $rand", "<!", $newText);
        $newText = str_replace("< !--", "<!--", $newText);

        return str_replace("<    !--", "< !--", $newText);
    }

    /**
     * Set cookie domain with .domain.ext for multi subdomain
     *
     * @param string $domain
     * @return string|null|false $return domain ( .domain.com )
     */
    public static function splitCrossDomain(string $domain): bool|string|null
    {
        // make it domain lower
        $domain = strtolower($domain);
        $domain = preg_replace('~^\s*(?:(http|ftp)s?|sftp|xmp)://~i', '', $domain);
        $domain = preg_replace('~/.*$~', '', $domain);
        $is_ip = filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if (!$is_ip) {
            $is_ip = filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        }
        if (!$is_ip) {
            $parse = parse_url('http://' . $domain . '/');
            $domain = $parse['host'] ?? null;
            if ($domain === null) {
                return null;
            }
        }
        if (!preg_match('/^((\[[0-9a-f:]+])|(\d{1,3}(\.\d{1,3}){3})|[a-z0-9\-.]+)(:\d+)?$/i', $domain)
            || $is_ip
            || $domain == '127.0.0.1'
            || $domain == 'localhost'
        ) {
            return $domain;
        }
        $domain = preg_replace('~[\~!@#$%^&*()+`{}\]\[/\';<>,\"?=|\\\]~', '', $domain);
        if (str_contains($domain, '.')) {
            if (preg_match('~(.*\.)+(.*\.)+(.*)~', $domain)) {
                $return = '.' . preg_replace('~(.*\.)+(.*\.)+(.*)~', '$2$3', $domain);
            } else {
                $return = '.' . $domain;
            }
        } else {
            $return = $domain;
        }
        return $return;
    }

    /**
     * @param string $slug
     * @return string
     */
    public static function normalizeSlug(string $slug): string
    {
        $slug = str_replace(
            array_keys(self::$conversionTables),
            array_values(self::$conversionTables),
            $slug
        );
        $slug = preg_replace('~[^a-z0-9\-_]~i', '-', trim($slug));
        $slug = preg_replace('~([\-_])+~', '$1', $slug);

        return trim($slug, '-_');
    }

    /**
     * @param string $slug
     * @param array $slugCollections
     * @return string
     */
    public static function uniqueSlug(string $slug, array $slugCollections): string
    {
        $separator = '-';
        $inc = 1;
        $slug = self::normalizeSlug($slug);
        $baseSlug = $slug;
        while (in_array($slug, $slugCollections)) {
            $slug = $baseSlug . $separator . $inc++;
        }
        return $slug;
    }

    /**
     * @param string $slug
     * @param callable $callable must be returning true for valid
     * @return string
     */
    public static function uniqueSlugCallback(string $slug, callable $callable): string
    {
        $separator = '-';
        $inc = 1;
        $slug = self::normalizeSlug($slug);
        $baseSlug = $slug;
        while (!$callable($slug)) {
            $slug = $baseSlug . $separator . $inc++;
        }

        return $slug;
    }

    /**
     * @param string $fileName
     * @param string $directory
     * @param bool $allowedSpace
     * @return string|false false if directory does not exist,
     *                      returning full path for save.
     */
    public static function resolveFileDuplication(
        string $fileName,
        string $directory,
        bool $allowedSpace = false
    ): bool|string {
        if (!is_dir($directory)) {
            return false;
        }

        $directory = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $directory);
        $directory = realpath($directory)?:rtrim($directory, DIRECTORY_SEPARATOR);
        $directory .= DIRECTORY_SEPARATOR;
        $paths = explode('.', $fileName);
        $extension = null;
        if (count($paths) > 1) {
            $extension = array_pop($paths);
        }
        $fileName = implode($paths);
        if ($extension && !preg_match('/^[a-z0-9A-Z_-]+$/', $extension)) {
            $extension = null;
        }
        $extension = $extension?: null;
        $fileName = self::normalizeFileName($fileName);
        if (!$allowedSpace) {
            $fileName = str_replace(' ', '-', $fileName);
        }
        $c = 1;
        $filePath = $extension? "$fileName.$extension": $fileName;
        while (file_exists($directory . $filePath)) {
            $newFile = "$fileName-". $c++;
            $filePath = $extension? "$newFile.$extension": $newFile;
        }

        clearstatcache(true);

        return $directory . $filePath;
    }

    /**
     * Convert number of bytes the largest unit bytes will fit into.
     *
     * It is easier to read 1 kB than 1024 bytes and 1 MB than 1048576 bytes. Converts
     * number of bytes to human-readable number by taking the number of that unit
     * that the bytes will go into it. Supports TB value.
     *
     * Please note that integers in PHP are limited to 32 bits, unless they are on
     * 64 bit architecture, then they have 64 bit size. If you need to place the
     * larger size then what PHP integer type will hold, then use a string. It will
     * be converted to a double, which should always have 64 bit length.
     *
     * Technically the correct unit names for powers of 1024 are KiB, MiB etc.
     *
     * @param int|string $bytes         Number of bytes. Note max integer size for integers.
     * @param int $decimals      Optional. Precision of number of decimal places. Default 0.
     * @param string     $decimalPoint Optional decimal point
     * @param string $thousandSeparator Optional thousand separator
     *
     * @return string|false False on failure. Number string on success.
     */
    public static function sizeFormat(
        int|string $bytes,
        int $decimals = 0,
        string $decimalPoint = '.',
        string $thousandSeparator = ','
    ): bool|string {
        $quanta = [
            // ========================= Origin ====
            'TB' => 1099511627776,  // pow( 1024, 4)
            'GB' => 1073741824,     // pow( 1024, 3)
            'MB' => 1048576,        // pow( 1024, 2)
            'kB' => 1024,           // pow( 1024, 1)
            'B'  => 1,              // pow( 1024, 0)
        ];
        /**
         * Check and did
         */
        foreach ($quanta as $unit => $mag) {
            if (doubleval($bytes) >= $mag) {
                return number_format(
                    ($bytes / $mag),
                    $decimals,
                    $decimalPoint,
                    $thousandSeparator
                ). ' ' . $unit;
            }
        }

        return false;
    }

    /**
     * @param string $size
     * @return int
     */
    public static function returnBytes(string $size) : int
    {
        $size = trim($size) ?: 0;
        if (!$size) {
            return 0;
        }

        $last = strtolower(substr($size, -1));
        $size = intval($size);
        switch ($last) {
            case 't':
                $size *= 1024;
                $size *= 1024;
                $size *= 1024;
                $size *= 1024;
                break;
            case 'g':
                $size *= 1024;
                $size *= 1024;
                $size *= 1024;
                break;
            case 'm':
                $size *= 1024;
                $size *= 1024;
                break;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    /**
     * @return int
     */
    public static function getMaxUploadSize() : int
    {
        $data = [
            self::returnBytes(ini_get('post_max_size')),
            self::returnBytes(ini_get('upload_max_filesize')),
            (self::returnBytes(ini_get('memory_limit')) - 2048),
        ];
        foreach ($data as $key => $v) {
            if ($v <= 0) {
                unset($data[$key]);
            }
        }

        return min($data);
    }
}
