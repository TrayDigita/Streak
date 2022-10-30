<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller\Abstracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Json\ApiCreator;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\HttpThrowableException;
use TrayDigita\Streak\Source\Traits\SimpleDocumentCreator;
use TrayDigita\Streak\Source\Traits\TranslationMethods;
use WoohooLabs\Yin\JsonApi\JsonApi;

abstract class AbstractResponse extends AbstractContainerization
{
    use HttpThrowableException,
        TranslationMethods,
        EventsMethods,
        SimpleDocumentCreator;

    /**
     * @var string
     */
    protected string $jsonContentType = 'application/json';

    /**
     * @var string
     */
    protected string $jsonApiContentType = 'application/vnd.api+json';

    /**
     * @var string
     */
    protected string $htmlContentType = 'text/html';

    /**
     * @var string
     */
    protected string $plainContentType = 'text/plain';

    /**
     * @var string
     */
    protected string $xmlContentType = 'application/xml';

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     * @noinspection PhpUnused
     */
    public function toJsonResponse(ResponseInterface $response) : ResponseInterface
    {
        return $response
            ->withHeader('Content-Type', $this->jsonContentType);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     * @noinspection PhpUnused
     */
    public function toJsonApiResponse(ResponseInterface $response) : ResponseInterface
    {
        return $response
            ->withHeader('Content-Type', $this->jsonApiContentType);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     * @noinspection PhpUnused
     */
    public function toHtmlResponse(ResponseInterface $response) : ResponseInterface
    {
        return $response
            ->withHeader('Content-Type', $this->htmlContentType);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function toPlainTextResponse(ResponseInterface $response) : ResponseInterface
    {
        return $response
            ->withHeader('Content-Type', $this->plainContentType);
    }

    /**
     * @param ServerRequestInterface|null $request
     * @param ResponseInterface|null $response
     *
     * @return JsonApi
     * @noinspection PhpUnused
     */
    public function createJsonApi(
        ?ServerRequestInterface $request = null,
        ?ResponseInterface $response = null
    ) : JsonApi {
        $apiCreator = $this->getContainer(ApiCreator::class);
        return $apiCreator
            ->createJsonApi(
                $response,
                $apiCreator->createJsonApiRequest($request)
            );
    }
}
