<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Views\Html;

use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Views\DefaultRenderView;
use TrayDigita\Streak\Source\Views\ExceptionsRenderView;
use TrayDigita\Streak\Source\Views\MultiRenderView;

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
    public function createDefaultRenderView(): DefaultRenderView
    {
        return $this->eventDispatch(
            'Renderer:default',
            new DefaultRenderView($this->getContainer())
        );
    }

    /**
     * @return MultiRenderView
     */
    public function createMultiRenderView() : MultiRenderView
    {
        return $this->eventDispatch(
            'Renderer:view',
            new MultiRenderView($this->getContainer())
        );
    }
}
