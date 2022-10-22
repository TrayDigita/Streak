<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Abilities;

interface Scannable
{
    /**
     * Doing scan
     */
    public function scan();

    /**
     * Check whether has been scanned
     *
     * @return bool
     */
    public function scanned() : bool;
}
