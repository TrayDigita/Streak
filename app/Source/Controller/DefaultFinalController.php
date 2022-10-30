<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;

final class DefaultFinalController extends AbstractController
{
    /**
     * @return string
     */
    public function getRoutePattern(): string
    {
        return '*';
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $params
     *
     * @return ResponseInterface
     */
    public function doRouting(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params = []
    ): ResponseInterface {
        return $this->eventDispatch(
            'Controller:DefaultFinalController',
            $response,
            $request,
            $params
        );
    }
}
