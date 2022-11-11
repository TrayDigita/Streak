<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Json;

use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionProperty;
use Slim\Exception\HttpSpecializedException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Application;
use TrayDigita\Streak\Source\Helper\Http\Code;
use TrayDigita\Streak\Source\Json\Schema\ErrorSource;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\TranslationMethods;
use WoohooLabs\Yang\JsonApi\Request\ResourceObject;
use WoohooLabs\Yin\JsonApi\Exception\ExceptionFactoryInterface;
use WoohooLabs\Yin\JsonApi\Request\JsonApiRequest;
use WoohooLabs\Yin\JsonApi\Request\JsonApiRequestInterface;
use WoohooLabs\Yin\JsonApi\Schema\Document\ErrorDocument;
use WoohooLabs\Yin\JsonApi\Schema\Error\Error;
use WoohooLabs\Yin\JsonApi\JsonApi;
use WoohooLabs\Yin\JsonApi\Schema\JsonApiObject;

class ApiCreator extends AbstractContainerization
{
    const VERSION = "1.1";
    use EventsMethods,
        TranslationMethods;

    /**
     * @var string
     */
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
            : ($code >= 400 ? $code : 500);
        if ($httpCode !== 500) {
            $newTitle = Code::statusMessage($code);
            $newTitle = $newTitle ? $this->translate($newTitle) : null;
            if (($hasMethod = method_exists($exception, 'getTitle'))
                || (
                    property_exists($exception, 'title')
                    && (new ReflectionProperty($exception, 'title'))->isPublic())
            ) {
                $exceptionTitle = $hasMethod ? $exception->getTitle() : $exception->{'title'};
                $newTitle       = !is_string($exceptionTitle) ? $newTitle : $exceptionTitle;
            }
            $title = $newTitle ? $this->translate($newTitle) : $title;
        }

        if ($code < 400) {
            $response = $response->withStatus($httpCode);
        }

        $jsonApi->setResponse($response);
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
            $response->getStatusCode()
        );
    }
}
