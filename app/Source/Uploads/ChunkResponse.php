<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Uploads;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Hash;
use TrayDigita\Streak\Source\Helper\Generator\UUID;
use TrayDigita\Streak\Source\Helper\Http\Code;
use TrayDigita\Streak\Source\Json\ApiCreator;
use TrayDigita\Streak\Source\Json\Schema\ErrorSource;
use TrayDigita\Streak\Source\SystemInitialHandler;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use TrayDigita\Streak\Source\Traits\SimpleDocumentCreator;
use TrayDigita\Streak\Source\Traits\TranslationMethods;
use TrayDigita\Streak\Source\Uploads\Exceptions\FileException;
use WoohooLabs\Yin\JsonApi\Schema\Link\ErrorLinks;
use WoohooLabs\Yin\JsonApi\Schema\Link\Link;

class ChunkResponse extends AbstractContainerization
{
    use SimpleDocumentCreator,
        EventsMethods,
        TranslationMethods;

    public function __construct(Container|AbstractContainerization $container)
    {
        parent::__construct(
            $container instanceof AbstractContainerization
                ? $container->getContainer()
                : $container
        );
    }

    /**
     * @param string $parameterName
     * @param string $target
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param UploadedFileInterface $uploadedFile
     * @param bool $overrideIfExists
     * @param bool $increment
     * @param null $handler
     *
     * @return ResponseInterface
     * @throws Throwable
     */
    public function handle(
        string $parameterName,
        string $target,
        ServerRequestInterface $request,
        ResponseInterface $response,
        UploadedFileInterface $uploadedFile,
        bool $overrideIfExists = false,
        bool $increment = true,
        &$handler = null
    ) : ResponseInterface {
        // set header unit accept
        $requestId  = $request->getHeaderLine('X-Request-Id');
        $requestId  = !is_string($requestId)
            ? null
            : $requestId;

        // chunk
        $chunkObject  = new Chunk($this->getContainer());
        // set response
        $body = $response->getBody();
        if ($body->isSeekable() && $body->isWritable()) {
            $body->rewind();
            // reset content
            $body->write('');
        } else {
            $body = $this
                ->getContainer(SystemInitialHandler::class)
                ->getCreateLastResponseStream()
                ->getBody();
        }
        $response = $response->withBody($body);

        // api
        $apiCreator = $this->getContainer(ApiCreator::class);
        $jsonApi    =  $apiCreator->createJsonApi(
            $response,
            $apiCreator->createJsonApiRequest($request)
        );
        $responder = $jsonApi->respond();
        $errorDocument = $apiCreator->createErrorDocument();
        $contentRange = $request->getHeaderLine('Content-Range');
        // default
        $errStatusCode = Code::REQUESTED_RANGE_NOT_SATISFIABLE;
        $error         = $apiCreator->createError();
        $error->setStatus("$errStatusCode");
        $error->setDetail(
            $this->translate(
                'Content-Range header is not fulfilled.'
            )
        );

        $about = new Link('/en-US/docs/Web/HTTP/Headers/Content-Range');
        $errorLink = new ErrorLinks('https://developer.mozilla.org', $about);
        preg_match(
            '~(?P<unit>[a-z]+)?\s+(?P<start>[0-9]+)\s*-\s*(?P<end>[0-9]+)\s*/\s*(?P<size>[0-9]+)\s*$~i',
            $contentRange,
            $match
        );

        $unit = $match['unit']??'bytes';
        $unit = strtolower($unit);

        $start = (int) $match['start']??null;
        $end   = (int) $match['end']??null;
        $size  = (int) $match['size']??null;
        $meta = [
            'request' => [
                'id' => $requestId,
                'range' => [
                    'unit' => $unit,
                    'start' => $start,
                    'end' => $end,
                    'size' => $size,
                ]
            ]
        ];
        $newMetaRequest = $this->eventDispatch(
            'ChunkResponse:meta:response',
            $meta['request'],
            $meta
        );
        if (!is_array($newMetaRequest)) {
            $newMetaRequest = $meta['request'];
        }
        $meta['request'] = $newMetaRequest;
        $error->setSource(ErrorSource::fromHeader('Content-Range'));
        $error->setMeta($meta);

        // if Content-Range is not valid
        if (empty($match)) {
            $error->setLinks($errorLink);
            $errorDocument->addError($error);
            return $chunkObject->serverWithResponseHeader(
                $responder
                    ->genericError($errorDocument)
                    ->withStatus($errStatusCode)
            );
        }
        // if unit is not bytes
        if ($unit !== 'bytes') {
            $error->setLinks($errorLink);
            $errorDocument->addError($error);
            $error->setDetail(
                $this->translate(
                    'Server only accept bytes unit.'
                )
            );
            return $jsonApi
                ->respond()
                ->genericError($errorDocument)
                ->withStatus($errStatusCode);
        }

        // start size is bigger than 0 and does not have request id
        if (!$requestId && $start > 0) {
            $error->setLinks($errorLink);
            $errorDocument->addError($error);
            $error->setDetail(
                $this->translate(
                    'Content-Range start bytes must be zero without Request-Id.'
                )
            );
            return $chunkObject->serverWithResponseHeader(
                $responder
                    ->genericError($errorDocument)
                    ->withStatus($errStatusCode)
            );
        }

        $clientFileName = $uploadedFile->getClientFilename();
        $clientFileNameMd5 = $clientFileName
            ? $this->getContainer(Hash::class)->md5($clientFileName)
            : null;
        if (!$clientFileNameMd5) {
            $errorDocument->addError($error);
            $error->setDetail(
                $this->translate(
                    'Invalid uploaded file name.'
                )
            );
            $error->setSource(ErrorSource::fromParameter(
                $parameterName
            ));
            return $chunkObject->serverWithResponseHeader(
                $responder->genericError($errorDocument)
            );
        }

        if ($requestId) {
            $requestIdArray = explode('-', $requestId);
            $id = array_pop($requestIdArray)?:null;
            $hash = implode('-', $requestIdArray)??null;
            $version = ! $id || $hash !== $clientFileNameMd5
                ? null
                : UUID::validate($id);
            if ($version !== UUID::V4) {
                $errorDocument->addError($error);
                $error->setDetail(
                    $this->translate(
                        'Invalid Request-Id header.'
                    )
                );
                return $chunkObject->serverWithResponseHeader(
                    $responder->genericError($errorDocument)
                );
            }
        }
        // uuid
        $id = $id??UUID::v4();

        // if content-range is out of range
        // (total size) less than (end size)
        // or
        // (end size) less than (start size)
        if ($start > $end || $size < $end) {
            $error->setDetail(
                $this->translate(
                    'Range bytes is out of range.'
                )
            );
            $errorDocument->addError($error);
            return $chunkObject->serverWithResponseHeader(
                $responder
                    ->genericError($errorDocument)
                    ->withStatus($errStatusCode)
            );
        }

        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $errorDocument->addError($error);
            $error->setDetail(
                $this->translate(
                    'There was an error with uploaded file.'
                )
            );

            return $chunkObject->serverWithResponseHeader(
                $responder
                    ->genericError($errorDocument)
                    ->withStatus($errStatusCode)
            );
        }

        $stream     = $uploadedFile->getStream();
        $streamSize = $stream->getSize();
        if ($size > $streamSize) {
            $errorDocument->addError($error);
            $error->setDetail(
                $this->translate(
                    'Range size is bigger than file size.'
                )
            );
            return $chunkObject->serverWithResponseHeader(
                $responder
                ->genericError($errorDocument)
                ->withStatus($errStatusCode)
            );
        }

        $endSize = $end - $start;
        if ($endSize < $streamSize) {
            $errorDocument->addError($error);
            $error->setDetail(
                $this->translate(
                    'Uploaded file size is bigger than ending size.'
                )
            );
            return $chunkObject->serverWithResponseHeader(
                $responder
                    ->genericError($errorDocument)
                    ->withStatus($errStatusCode)
            );
        }

        $fileName     = $uploadedFile->getClientFilename();
        $requestId    = $requestId?:sprintf(
            '%s-%s',
            $id,
            $clientFileNameMd5
        );
        $identity     = sprintf('%s:%s', $requestId, $fileName);
        $handler      = new Handler($chunkObject, $uploadedFile, $identity);
        $document = $this->createSimpleDocument(
            $requestId,
            'upload'
        );
        try {
            $written  = $handler->start($start);
            $fileSize = $handler->getSize();
            $status   = $fileSize < $size ? 'processing' : 'complete';
            $document->setMeta($meta);
            $succeed = $handler->targetCacheFile;
            if ($status === 'complete') {
                $succeed = $handler->put(
                    $target,
                    $overrideIfExists,
                    $increment
                );
            }

            $target   = $succeed?:($handler->getLastTarget()?:$target);
            $baseName = basename($target);
            if (!is_string($succeed) || !file_exists($succeed)) {
                throw new FileException(
                    $target,
                    $this->translate(
                        'Could not save uploaded file.'
                    )
                );
            }

            $attribute = [
                'id' => $requestId,
                'written' => $written,
                'file' => $baseName,
                'size' => $fileSize,
                'status' => $status,
                'checksum'  => [
                    'sha1' => sha1_file($succeed),
                    'md5'  => md5_file($succeed)
                ],
            ];
            $newAttribute = $this->eventDispatch(
                'ChunkResponse:meta:response',
                $attribute,
                $meta
            );
            if (!is_array($newAttribute)) {
                $newAttribute = $attribute;
            }
            $attribute = $newAttribute;
            $document->setAttributes($attribute);
            return $chunkObject->serverWithResponseHeader(
                $responder
                    ->ok($document, $this)
                    ->withStatus(Code::CREATED)
            );
        } catch (RuntimeException $exception) {
            $errStatusCode = Code::NOT_IMPLEMENTED;
            $error->setStatus("$errStatusCode");
            $error->setDetail($exception->getMessage());
            $errorDocument->addError($error);
            return $chunkObject->serverWithResponseHeader(
                $responder
                    ->genericError($errorDocument)
                    ->withStatus($errStatusCode)
            );
        } catch (Throwable $exception) {
            return $chunkObject->serverWithResponseHeader(
                $this
                    ->getContainer(SystemInitialHandler::class)
                    ->renderError(
                        $exception,
                        $request,
                        $response
                    )
            );
        }
    }
}
