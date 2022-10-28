<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

class NumericConverter
{
    /**
     * @param int $megaBytes
     *
     * @return int
     */
    public static function megaByteToBytes(int $megaBytes) : int
    {
        return $megaBytes * 1024 * 1048576;
    }
}
