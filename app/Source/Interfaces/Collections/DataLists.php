<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Collections;

interface DataLists
{
    /**
     * Getting all key
     *
     * @return array
     */
    public function keys() : array;

    /**
     * @return array
     */
    public function all() : array;
}
