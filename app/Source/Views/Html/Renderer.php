<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Views\Html;

use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Views\DefaultRenderView;
use TrayDigita\Streak\Source\Views\ExceptionsRenderView;

class Renderer extends AbstractContainerization
{
    use EventsMethods;

    /**
     * @param Throwable|HttpSpecializedException $exception
     *
     * @return ExceptionsRenderView
     */
    public function createExceptionRenderView(
        Throwable|HttpSpecializedException $exception
    ) : ExceptionsRenderView {
        return $this->eventDispatch(
            'Renderer:exception',
            new ExceptionsRenderView($exception, $this->getContainer())
        );
    }

    /**
     * @return DefaultRenderView
     */
    public function createRenderView(): DefaultRenderView
    {
        return $this->eventDispatch(
            'Renderer:view',
            new DefaultRenderView($this->getContainer())
        );
    }
}
