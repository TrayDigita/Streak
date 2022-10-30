<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpGoneException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpSpecializedException;
use Slim\Exception\HttpUnauthorizedException;
use Throwable;

trait HttpThrowableException
{
    use TranslationMethods;

    public function convertTranslationException(
        HttpSpecializedException $exception
    ): HttpSpecializedException {
        if (get_class($exception) === HttpMethodNotAllowedException::class) {
            $message = $this->translate('Method not allowed.');
        } else {
            $message = $this->translate($exception->getMessage());
        }
        $className = get_class($exception);
        $exception = new $className(
            $exception->getRequest(),
            $message,
            $exception->getPrevious()
        );
        $exception->setTitle($this->translate($exception->getTitle()));
        $exception->setDescription($this->translate($exception->getDescription()));
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }

    public function getHttpNotFoundException(
        ServerRequestInterface $request,
        ?string $message = null,
        ?Throwable $previous = null
    ) : HttpNotFoundException {
        $exception = new HttpNotFoundException(
            $request,
            $message??$this->translate('Not found.'),
            $previous
        );
        $exception->setTitle($this->translate('404 Not Found'));
        $exception->setDescription(
            $this
            ->translate(
                'The requested resource could not be found. Please verify the URI and try again.'
            )
        );
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }

    public function getHttpMethodNotAllowedException(
        ServerRequestInterface $request,
        ?string $message = null,
        ?Throwable $previous = null,
        ?array $allowedMethods = []
    ): HttpMethodNotAllowedException {
        $exception = new HttpMethodNotAllowedException(
            $request,
            $message??$this->translate('Method not allowed.'),
            $previous
        );

        $exception->setAllowedMethods($allowedMethods);
        $exception->setTitle($this->translate('405 Method Not Allowed'));
        $exception->setDescription(
            $this
                ->translate(
                    'The request method is not supported for the requested resource.'
                )
        );
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }

    public function getHttpBadRequestException(
        ServerRequestInterface $request,
        ?string $message = null,
        ?Throwable $previous = null
    ): HttpBadRequestException {
        $exception = new HttpBadRequestException(
            $request,
            $message??$this->translate('Bad request.'),
            $previous
        );
        $exception->setTitle($this->translate('400 Bad Request'));
        $exception->setDescription(
            $this
                ->translate(
                    'The server cannot or will not process the request due to an apparent client error.'
                )
        );
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }

    public function getHttpUnauthorizedException(
        ServerRequestInterface $request,
        ?string $message = null,
        ?Throwable $previous = null
    ): HttpUnauthorizedException {
        $exception = new HttpUnauthorizedException(
            $request,
            $message??$this->translate('Unauthorized.'),
            $previous
        );
        $exception->setTitle($this->translate('401 Unauthorized'));
        $exception->setDescription(
            $this
                ->translate(
                    'The request requires valid user authentication.'
                )
        );
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }

    public function getHttpNotImplementedException(
        ServerRequestInterface $request,
        ?string $message = null,
        ?Throwable $previous = null
    ): HttpNotImplementedException {
        $exception = new HttpNotImplementedException(
            $request,
            $message??$this->translate('Not implemented.'),
            $previous
        );
        $exception->setTitle($this->translate('501 Not Implemented'));
        $exception->setDescription(
            $this
                ->translate(
                    'The server does not support the functionality required to fulfill the request.'
                )
        );
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }

    public function getHttpInternalServerErrorException(
        ServerRequestInterface $request,
        ?string $message = null,
        ?Throwable $previous = null
    ): HttpInternalServerErrorException {
        $exception = new HttpInternalServerErrorException(
            $request,
            $message??$this->translate('Internal server error.'),
            $previous
        );
        $exception->setTitle($this->translate('500 Internal Server Error'));
        $exception->setDescription(
            $this
                ->translate(
                    'Unexpected condition encountered preventing server from fulfilling request.'
                )
        );
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }

    public function getHttpGoneException(
        ServerRequestInterface $request,
        ?string $message = null,
        ?Throwable $previous = null
    ): HttpGoneException {
        $exception = new HttpGoneException(
            $request,
            $message??$this->translate('Gone.'),
            $previous
        );
        $exception->setTitle($this->translate('410 Gone'));
        $exception->setDescription(
            $this
                ->translate(
                    'The target resource is no longer available at the origin server.'
                )
        );
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }

    public function getHttpForbiddenException(
        ServerRequestInterface $request,
        ?string $message = null,
        ?Throwable $previous = null
    ): HttpForbiddenException {
        $exception = new HttpForbiddenException(
            $request,
            $message??$this->translate('Forbidden.'),
            $previous
        );
        $exception->setTitle($this->translate('403 Forbidden'));
        $exception->setDescription(
            $this
                ->translate(
                    'You are not permitted to perform the requested operation.'
                )
        );
        /** @noinspection PhpUndefinedFieldInspection */
        $exception->translated = true;
        return $exception;
    }
}
