<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Json;

use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\i18n\Translator;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;
use WoohooLabs\Yang\JsonApi\Request\ResourceObject;
use WoohooLabs\Yin\JsonApi\Exception\ExceptionFactoryInterface;
use WoohooLabs\Yin\JsonApi\Request\JsonApiRequest;
use WoohooLabs\Yin\JsonApi\Request\JsonApiRequestInterface;
use WoohooLabs\Yin\JsonApi\Schema\Document\ErrorDocument;
use WoohooLabs\Yin\JsonApi\Schema\Error\Error;
use WoohooLabs\Yin\JsonApi\JsonApi;
use WoohooLabs\Yin\JsonApi\Schema\Error\ErrorSource;
use WoohooLabs\Yin\JsonApi\Schema\JsonApiObject;

class ApiCreator extends AbstractContainerization
{
    const VERSION = "1.1";
    use EventsMethods,
        TranslationMethods;
    protected static string $version = self::VERSION;

    /**
     * @return string
     */
    public static function getVersion(): string
    {
        return self::$version;
    }

    /**
     * @param string $version
     */
    public static function setVersion(string $version): void
    {
        self::$version = $version;
    }

    /**
     * @param ?ResponseInterface $response
     * @param ?JsonApiRequest $jsonApiRequest
     * @param ?ExceptionFactoryInterface $exceptionFactory
     *
     * @return JsonApi
     */
    public function createJsonApi(
        ?ResponseInterface $response = null,
        ?JsonApiRequest $jsonApiRequest = null,
        ?ExceptionFactoryInterface $exceptionFactory = null
    ) : JsonApi {
        return new JsonApi(
            $jsonApiRequest??$this->getContainer(JsonApiRequestInterface::class),
            $response??$this->getContainer(ResponseFactoryInterface::class)->createResponse(),
            $exceptionFactory??$this->getContainer(ExceptionFactoryInterface::class),
            $this->getContainer(EncodeDecode::class)
        );
    }

    public function createJsonApiRequest(
        ?ServerRequestInterface $request = null,
        ?ExceptionFactoryInterface $exceptionFactory = null
    ): JsonApiRequest {
        return new JsonApiRequest(
            $request??$this->getContainer(ServerRequestInterface::class),
            $exceptionFactory??$this->getContainer(ExceptionFactoryInterface::class),
            $this->getContainer(EncodeDecode::class),
        );
    }

    #[Pure] public function createJsonApiObject() : JsonApiObject
    {
        return new JsonApiObject(self::getVersion());
    }

    public function createErrorDocument(?Error $error = null) : ErrorDocument
    {
        $errorDocument = (new ErrorDocument())->setJsonApi($this->createJsonApiObject());
        if ($error) {
            $errorDocument->addError($error);
        }
        return $errorDocument;
    }

    #[Pure] public function createResourceObject(
        string $type,
        string $id = ''
    ) : ResourceObject {
        return new ResourceObject($type, $id);
    }

    #[Pure] public function createError() : Error
    {
        return new Error();
    }

    /**
     * @param Throwable|HttpSpecializedException $exception
     * @param ?ResponseInterface $response
     * @param ?JsonApiRequest|null $jsonApiRequest
     * @param ?ExceptionFactoryInterface $exceptionFactory
     *
     * @return ResponseInterface
     */
    public function responseError(
        Throwable|HttpSpecializedException $exception,
        ?ResponseInterface $response = null,
        ?JsonApiRequest $jsonApiRequest = null,
        ?ExceptionFactoryInterface $exceptionFactory = null
    ) : ResponseInterface {
        $jsonApi = $this->createJsonApi($response, $jsonApiRequest, $exceptionFactory);
        $application = $this->getContainer(Application::class);
        // $error->setId(spl_object_hash($exception));
        $title = $exception instanceof HttpSpecializedException
            ? $exception->getTitle()
            : $this->translate("500 Internal Server Error");
        $description = $exception instanceof HttpSpecializedException
            ? ($exception->getDescription()?:$exception->getMessage())
            : $exception->getMessage();
        $code = $response->getStatusCode();
        $httpCode = $exception instanceof HttpSpecializedException
            ? $exception->getCode()
            : 500;
        if ($code < 400) {
            $jsonApi->setResponse($response->withStatus($httpCode));
        }
        $error = $this->createError()
            ->setTitle($title)
            ->setDetail($description)
            ->setStatus((string) $httpCode);
        $errorDocument = $this->createErrorDocument($error);
        if (!$application->isProduction()) {
            $error->setSource(new ErrorSource('exception', get_class($exception)));
            if ($application->isDebug()) {
                $error->setMeta([
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTrace()
                ]);
            }
        }

        return $jsonApi->respond()->genericError(
            $errorDocument,
            $code
        );
    }
}
