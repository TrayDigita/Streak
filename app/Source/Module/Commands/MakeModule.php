<?php
/**
 * @todo Completion Make Module
 */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module\Commands;

use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\MakeClassCommand;

class MakeModule extends MakeClassCommand
{
    protected string $command = 'module';
    protected string $type = 'module';

    protected function doGeneration(string $namespace, string $name, SymfonyStyle $symfonyStyle): int
    {
        return self::SUCCESS;
    }
}
