<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller\Abstracts;

abstract class AbstractJsonController extends AbstractController
{
    /**
     * @return string
     */
    public function getDefaultResultContentType() : string
    {
        return 'application/vnd.api+json';
    }
}
