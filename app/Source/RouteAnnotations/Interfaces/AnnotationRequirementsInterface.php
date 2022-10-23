<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\RouteAnnotations\Interfaces;

interface AnnotationRequirementsInterface
{
    public function getPath() : string;
    public function getCondition() : ?string;
    public function getRequirements() : array;
}
