<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\RouteAnnotations\Abstracts;

use JetBrains\PhpStorm\Pure;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractResponse;

class AnnotationController extends AbstractResponse
{
    #[Pure] final public function __construct(Container $container)
    {
        parent::__construct($container);
    }
}
