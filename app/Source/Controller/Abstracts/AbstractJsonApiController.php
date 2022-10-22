<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller\Abstracts;

use TrayDigita\Streak\Source\StoragePath;

abstract class AbstractJsonApiController extends AbstractJsonController
{
    public function getGroupRoutePattern(): string
    {
        $path = $this->getContainer(StoragePath::class)->getApiPath();
        $api = $this->eventDispatch('Controller:api:path', $path);
        if (is_string($api) && trim($api) !== '') {
            $api = preg_replace(
                ['~[\sa-z0-9\-_\/]~', '~[\\\/]+~'],
                ['', '/'],
                trim($api)
            );
            $path = trim($api, '/')?:$path;
        }
        return "/{groupRoute: $path}";
    }
}
