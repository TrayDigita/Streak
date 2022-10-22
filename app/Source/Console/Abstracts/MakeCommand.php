<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Console\Abstracts;

abstract class MakeCommand extends NamespaceCommand
{
    protected string $namespace = 'make';
}
