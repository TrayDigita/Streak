<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Views\Html;

use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Views\DefaultView;
use TrayDigita\Streak\Source\Views\ExceptionsView;

class Renderer extends AbstractContainerization
{
    /**
     * @param Throwable|HttpSpecializedException $exception
     *
     * @return ExceptionsView
     */
    public function exceptionView(
        Throwable|HttpSpecializedException $exception
    ) : ExceptionsView {
        return new ExceptionsView($exception, $this->getContainer());
    }

    public function defaultView(): DefaultView
    {
        return new DefaultView($this->getContainer());
    }
}
