<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\RouteAnnotations\Annotation;

use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\StoragePath;

/**
 * Annotation class for @Api().
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class Api extends Route
{
    /**
     * @param array $data An array of key/value parameters
     */
    public function __construct(array $data)
    {
        if (isset($data['value'])) {
            $data['path'] = $data['value'];
            unset($data['value']);
        }
        $api = Container::getInstance()->get(StoragePath::class)->getApiPath();
        $path = $data['path']??'';
        if ($path !== '' && ($path[0]??'') !== '/') {
            $path = "/$path";
        }
        $data['path'] = "/$api$path";
        parent::__construct($data);
    }
}
