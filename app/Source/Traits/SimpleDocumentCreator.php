<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use JetBrains\PhpStorm\Pure;
use Stringable;
use TrayDigita\Streak\Source\Json\SimpleDocument;
use WoohooLabs\Yin\JsonApi\Schema\JsonApiObject;
use WoohooLabs\Yin\JsonApi\Schema\Link\DocumentLinks;

trait SimpleDocumentCreator
{
    #[Pure] public function createSimpleDocument(
        int|float|string|Stringable $id,
        string $type,
        array $meta = [],
        ?array $attributes = null,
        ?array $relationships = null,
        ?DocumentLinks $links = null,
        ?JsonApiObject $jsonApi = null
    ) : SimpleDocument {
        return new SimpleDocument(
            $id,
            $type,
            $meta,
            $attributes,
            $relationships,
            $links,
            $jsonApi
        );
    }
}
