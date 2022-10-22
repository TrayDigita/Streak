<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Abilities;

interface Validatable
{
    /**
     * Is valid
     *
     * @return bool
     */
    public function valid() : bool;

    /**
     * Check whether is validated
     *
     * @return bool
     */
    public function validated() : bool;

    /**
     * validate
     */
    public function validate();
}
