<?php
/**
 * @todo Completion Make Middleware
 */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Middleware\Commands;

use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\MakeClassCommand;

class MakeMiddleware extends MakeClassCommand
{
    protected string $command = 'middleware';
    protected string $type = 'middleware';

    protected function doGeneration(string $namespace, string $name, SymfonyStyle $symfonyStyle): int
    {
        return self::SUCCESS;
    }
}
