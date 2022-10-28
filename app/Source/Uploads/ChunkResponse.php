<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Uploads;

use JetBrains\PhpStorm\Pure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Generator\UUID;
use TrayDigita\Streak\Source\Helper\Http\Code;
use TrayDigita\Streak\Source\Json\ApiCreator;
use TrayDigita\Streak\Source\Traits\SimpleDocumentCreator;

class ChunkResponse extends AbstractContainerization
{
    use SimpleDocumentCreator;

    #[Pure] public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    /**
     * @param string $target
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param UploadedFileInterface $uploadedFile
     * @param bool $overrideIfExists
     * @param bool $increment
     * @param null $handler
     *
     * @return ResponseInterface
     */
    public function handle(
        string $target,
        ServerRequestInterface $request,
        ResponseInterface $response,
        UploadedFileInterface $uploadedFile,
        bool $overrideIfExists = false,
        bool $increment = true,
        &$handler = null
    ) : ResponseInterface {
        $parsedBody   = (array)$request->getParsedBody();
        $requestUUID  = $request->getHeader('uuid');
        $requestUUID  = !is_string($requestUUID) || !UUID::validate($requestUUID)
            ? null
            : $requestUUID;
        $uuid         = $requestUUID?:UUID::v4();
        $chunkStatus  = (int)($parsedBody['chunk'] ?? 0);
        $chunksStatus = (int)($parsedBody['chunks'] ?? 0);
        $chunkObject  = new Chunk($this->getContainer());
        $fileName     = $uploadedFile->getClientFilename();
        $identity     = sprintf('%s:%s', $uuid, $fileName);
        $handler      = new Handler($chunkObject, $uploadedFile, $identity);

        $apiCreator = $this->getContainer(ApiCreator::class);
        $jsonApi    =  $apiCreator->createJsonApi(
            $response,
            $apiCreator->createJsonApiRequest($request)
        );

        $document = $this->createSimpleDocument(
            $uuid,
            'fileUpload'
        );
        $meta = [
            'message' => $chunkObject->translate('Processing your upload file.'),
            'request' => [
                'fileName' => $uploadedFile->getClientFilename(),
                'size'     => $uploadedFile->getSize(),
                'uuid'     => $requestUUID,
                'status'   => 'requested',
                'chunk'    => $chunkStatus,
                'chunks'   => $chunksStatus,
            ],
            'response' => [
                'fileName' => basename($target),
                'size'     => 0,
                'uuid'     => $uuid,
                'status'   => 'processing',
                'chunk'    => $chunkStatus,
                'chunks'   => $chunksStatus,
            ]
        ];

        $errStatusCode = Code::INTERNAL_SERVER_ERROR;
        $code = Code::OK;
        try {
            $handler->resume();
            if ($chunkStatus === 0 || $chunkStatus === ($chunksStatus - 1)) {
                $meta['response']['status'] = 'complete';
                $status = $handler->put(
                    $target,
                    $overrideIfExists,
                    $increment
                );
                $baseName = basename($handler->getLastTarget()?:$target);
                $meta['response']['file'] = $baseName;
                $code = Code::CREATED;
                if (!$status) {
                    $errStatusCode = Code::NOT_IMPLEMENTED;
                    throw new RuntimeException(
                        $chunkObject->translate(
                            'File successfully uploaded but not implemented.'
                        )
                    );
                }
            } else {
                $meta['response']['status'] = 'processing';
                $meta['response']['chunk']  = $chunkStatus+1;
                $meta['response']['chunks'] = $chunksStatus+1;
            }

            $meta['response']['size'] = $handler->getSize();
            $document->setMeta($meta);
            $response = $jsonApi
                ->respond()
                ->ok($document, $this)
                ->withStatus($code);
        } catch (Throwable $e) {
            if (!file_exists($handler->targetCacheFile)) {
                $meta['response']['size'] = filesize($handler->targetCacheFile);
            } elseif ($handler->getMovedFile() && file_exists($handler->getMovedFile())) {
                $meta['response']['size'] = filesize($handler->getMovedFile());
            }
            $errorDocument = $apiCreator->createErrorDocument();
            $error = $apiCreator->createError();
            $errorDocument->addError($error);
            $error->setMeta($meta);
            $error->setDetail($e->getMessage());
            $error->setStatus("$errStatusCode");
            $response = $jsonApi
                ->respond()
                ->genericError($errorDocument)
                ->withStatus($errStatusCode);
        }
        return $chunkObject->serverWithResponseHeader($response);
    }
}
