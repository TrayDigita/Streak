<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Console\Abstracts;

use JetBrains\PhpStorm\Pure;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Interfaces\ContainerizeInterface;
use TrayDigita\Streak\Source\Traits\Containerize;
use TrayDigita\Streak\Source\Traits\TranslationMethods;

abstract class NamespaceCommand extends Command implements ContainerizeInterface
{
    use Containerize,
        TranslationMethods;

    const NAMESPACE_SEPARATOR = ':';

    /**
     * Command namespace
     *
     * @var string
     */
    protected string $namespace = '';
    protected string $command = '';
    protected array $aliases = [];

    /**
     * @var bool
     */
    protected bool $isQuiet = false;

    /**
     * @param Container $container
     */
    public function __construct(private Container $container)
    {
        parent::__construct($this->getFullCommandName());
    }

    #[Pure] public function getFullCommandName() : string
    {
        $name = $this->getNamespace();
        if ($name) {
            $name .= self::NAMESPACE_SEPARATOR;
        }

        $name .= $this->getCommand();
        return $name;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    final protected function execute(InputInterface $input, OutputInterface $output) : int
    {
        $this->isQuiet = (
            $input->getOption('no-interaction')
            || $input->getOption('quiet')
        );
        return $this->doExecute(new SymfonyStyle($input, $output), $input, $output);
    }

    /**
     * @param SymfonyStyle $symfonyStyle
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @noinspection PhpUnusedParameterInspection
     */
    protected function doExecute(
        SymfonyStyle $symfonyStyle,
        InputInterface $input,
        OutputInterface $output
    ) : int {
        throw new LogicException(
            sprintf(
                $this->translate(
                    'You must override the %s method in the concrete command class.'
                ),
                'doExecute'
            )
        );
    }
}
