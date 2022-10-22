<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Json;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Stringable;
use WoohooLabs\Yin\JsonApi\Schema\Document\AbstractSimpleResourceDocument;
use WoohooLabs\Yin\JsonApi\Schema\JsonApiObject;
use WoohooLabs\Yin\JsonApi\Schema\Link\DocumentLinks;

class SimpleDocument extends AbstractSimpleResourceDocument
{
    /**
     * @var string
     */
    protected string $id;

    protected ?JsonApiObject $jsonApi;

    /**
     * @param int|float|string|Stringable $id
     * @param string $type
     * @param array|null $attributes
     * @param array|null $relationships
     * @param array $meta
     * @param DocumentLinks|null $links
     * @param JsonApiObject|null $jsonApi
     */
    #[Pure] public function __construct(
        int|float|string|Stringable $id,
        protected string $type,
        protected array $meta = [],
        protected ?array $attributes = [],
        protected ?array $relationships = null,
        protected ?DocumentLinks $links = null,
        ?JsonApiObject $jsonApi = null
    ) {
        $this->id = (string) $id;
        $this->jsonApi = $jsonApi??new JsonApiObject(ApiCreator::getVersion());
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param int|float|string|Stringable $id
     */
    public function setId(int|float|string|Stringable $id): void
    {
        $this->id = (string) $id;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    #[ArrayShape([
        'id' => "string",
        'type' => "string",
        'relationships' => "array|null",
        'attributes' => "array|null"
    ])] protected function getResource(): array
    {
        $data = [
            'id' => $this->id,
            'type' => $this->type
        ];
        if ($this->attributes !== null) {
            $data['attributes'] = $this->attributes;
        }
        if ($this->relationships !== null) {
            $data['relationships'] = $this->relationships;
        }
        return $data;
    }

    /**
     * @return ?array
     */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * @param array|null $attributes
     */
    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function setAttribute(string $name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function clearAttributes()
    {
        $this->attributes = null;
    }

    /**
     * @return ?array
     */
    public function getRelationships(): ?array
    {
        return $this->relationships;
    }

    /**
     * @param array $meta
     *
     * @return $this
     */
    public function setMeta(array $meta): static
    {
        $this->meta = $meta;
        return $this;
    }

    public function addMeta(string $name, $value) : static
    {
        $this->meta[$name] = $value;
        return $this;
    }

    public function removeMeta(string $name) : static
    {
        unset($this->meta[$name]);
        return $this;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param ?JsonApiObject $jsonApi
     *
     * @return $this
     */
    public function setJsonApi(?JsonApiObject $jsonApi): static
    {
        $this->jsonApi = $jsonApi;
        return $this;
    }

    public function getJsonApi(): ?JsonApiObject
    {
        return $this->jsonApi;
    }

    /**
     * @param DocumentLinks|null $links
     */
    public function setLinks(?DocumentLinks $links): void
    {
        $this->links = $links;
    }

    public function getLinks(): ?DocumentLinks
    {
        return $this->links;
    }
}
