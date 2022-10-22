<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Json;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Configurations;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Records\Collections;
use TrayDigita\Streak\Source\Traits\EventsMethods;
use WoohooLabs\Yin\JsonApi\Serializer\DeserializerInterface;
use WoohooLabs\Yin\JsonApi\Serializer\SerializerInterface;
use function json_decode;

class EncodeDecode extends AbstractContainerization implements SerializerInterface, DeserializerInterface
{
    use EventsMethods;

    public function __construct(
        Container $container,
        protected int $options = JSON_UNESCAPED_SLASHES,
        protected int $depth = 512,
        ?bool $prettyJson = null
    ) {
        parent::__construct($container);
        if ($prettyJson !== null) {
            $this->setPrettyPrinted($prettyJson);
        }
    }

    /**
     * @return int
     */
    public function getOptions(): int
    {
        return $this->options;
    }

    /**
     * @param int $options
     */
    public function setOptions(int $options): void
    {
        $this->options = $options;
    }

    /**
     * @return int
     */
    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * @param int $depth
     */
    public function setDepth(int $depth): void
    {
        $this->depth = $depth;
    }

    public function setPrettyPrinted(bool $prettify)
    {
        if ($prettify) {
            $this->options |= JSON_PRETTY_PRINT;
            return;
        }

        if ($this->options > JSON_PRETTY_PRINT) {
            $this->options -= JSON_PRETTY_PRINT;
        }
        $this->options |= ~JSON_PRETTY_PRINT;
    }

    public function encode(mixed $data, ?int $options = null, ?int $depth = null) : string|false
    {
        $originalOptions = $options;
        $options = $options??$this->getOptions();
        $depth = $depth??$this->getDepth();
        $newOptions = $this->eventDispatch(
            'Json:options',
            $options,
            $data,
            $depth,
            $originalOptions,
            $this
        );

        $data = $this->eventDispatch(
            'Json:data',
            $data,
            $options,
            $depth,
            $originalOptions,
            $this
        );
        if (is_array($data) && isset($data['jsonapi'])) {
            $jsonConfig = $this->getContainer(Configurations::class)->get('json');
            $display = !($jsonConfig instanceof Collections && $jsonConfig->get('displayVersion') === false);
            $display = $this->eventDispatch(
                'Json:enable:meta',
                $display,
                $data,
                $options,
                $depth,
                $originalOptions,
                $this
            );
            if ($display === false) {
                unset($data['jsonapi']);
            }
        }

        return json_encode($data, is_int($newOptions) ? $newOptions : $options, $depth);
    }

    /**
     * Decode json string, if detected object as array result when $associative set to true
     *
     * @param string $data
     * @param ?bool $associative
     * @param ?int $depth
     *
     * @return mixed
     */
    public function decode(
        string $data,
        ?bool $associative = true,
        ?int $depth = null
    ) : mixed {
        $depth = $depth??$this->getDepth();
        return json_decode($data, $associative, $depth);
    }

    /**
     * Decode json string, if detected object as array result
     *
     * @param string $data
     * @param ?int $depth
     *
     * @return mixed
     */
    public function decodeArray(
        string $data,
        ?int $depth = null
    ) : mixed {
        return $this->decode($data, true, $depth);
    }

    /**
     * Decode json string, if detected object as object result
     *
     * @param string $data
     * @param ?int $depth
     *
     * @return mixed
     */
    public function decodeObject(
        string $data,
        ?int $depth = null
    ) : mixed {
        return $this->decode($data, false, $depth);
    }

    public function serialize(ResponseInterface $response, array $content): ResponseInterface
    {
        if ($response->getBody()->isSeekable()) {
            $response->getBody()->rewind();
        }

        $body = $this->encode($content);
        if ($body !== false) {
            $response->getBody()->write($body);
        }

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return mixed
     */
    public function deserialize(ServerRequestInterface $request): mixed
    {
        return $this->decodeArray(
            $request->getBody()->__toString()
        );
    }

    public function getBodyAsString(ResponseInterface $response): string
    {
        return $response->getBody()->__toString();
    }
}
