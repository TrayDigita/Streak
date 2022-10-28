<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Traits\EventsMethods;

class ResponseFactory extends AbstractContainerization implements ResponseFactoryInterface
{
    use EventsMethods;

    public function createResponse(int $code = 200, string $reasonPhrase = '') : ResponseInterface
    {
        $stream = $this->getContainer(SystemInitialHandler::class)->createStream();
        return new Response($code, [], $stream, '1.1', $reasonPhrase);
    }
}
