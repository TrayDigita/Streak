<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Interfaces\Html;

use Stringable;

interface AttributeInterface extends Stringable
{
    /**
     * Html attribute name
     *
     * @return string
     */
    public function getName() : string;

    /**
     * Html attribute value
     *
     * @return string
     */
    public function getValue() : string;

    /**
     * @return string
     */
    public function build() : string;
}
