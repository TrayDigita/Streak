<?php
/** @noinspection PhpInternalEntityUsedInspection */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Console;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\ConsoleRunner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\OutputStyle;
use Throwable;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Interfaces\ContainerizeInterface;
use TrayDigita\Streak\Source\Traits\Containerize;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

class Runner extends Application implements ContainerizeInterface
{
    use TranslationMethods,
        Containerize;

    public function __construct(
        private Container $container,
        ?string $applicationName = null,
        ?string $applicationVersion = null
    ) {
        $this->setAutoExit(false);
        parent::__construct($applicationName, $applicationVersion);
    }
    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        $def = $this->getDefinition();
        $def->addOptions([
            new InputOption(
                '--enable-debug',
                '-d',
                InputOption::VALUE_NONE,
                $this->translate('Enable Debug')
            ),
        ]);
        $debug = $def->hasArgument('--enable-debug')
                 || $this
                     ->getContainer(\TrayDigita\Streak\Source\Application::class)
                     ->isDebug();
        if ($debug && Validator::isCli()) {
            ini_set('display_errors', '1');
            @ini_set('zend.assertions', '1');
            ini_set('assert.active', '1');
            ini_set('assert.warning', '0');
            ini_set('assert.exception', '1');
        }
        // add commands
        ConsoleRunner::addCommands($this, $this->getContainer(DependencyFactory::class));
        $input = $input??new ArgvInput();
        $output = $output??(Validator::isCli() ? $this->getContainer(ConsoleOutput::class) : null);
        if (!$output instanceof OutputStyle) {
            $output = Consolidation::createSymfonyConsoleOutput(
                $input,
                $output
            );
        }
        try {
            return parent::run($input, $output);
        } catch (Throwable $e) {
            $this->renderThrowable($e, $output);
            return Command::FAILURE;
        }
    }
}
