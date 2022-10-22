<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller\Abstracts;

use TrayDigita\Streak\Source\StoragePath;

abstract class AbstractMemberController extends AbstractController
{
    public function getGroupRoutePattern(): string
    {
        $path = $this->getContainer(StoragePath::class)->getMemberPath();
        $member = $this->eventDispatch('Controller:member:path', $path);
        if (is_string($member) && trim($member) !== '') {
            $member = preg_replace(
                ['~[\sa-z0-9\-_\/]~', '~[\\\/]+~'],
                ['', '/'],
                trim($member)
            );
            $path = trim($member, '/')?:$path;
        }
        return "/{groupRoute: $path}";
    }
}
