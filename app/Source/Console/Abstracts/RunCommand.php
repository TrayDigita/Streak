<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Console\Abstracts;

abstract class RunCommand extends NamespaceCommand
{
    protected string $namespace = 'run';

    protected function configure()
    {
        $this->setHelp(<<<EOT
The <info>%command.name%</info> run command

    <info>%command.full_name%</info>
EOT
        );
    }
}
