<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Console\Abstracts;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Container;
use TrayDigita\Streak\Source\Helper\Util\Validator;

abstract class MakeClassCommand extends MakeCommand
{
    /**
     * @var string
     */
    protected string $type = '';

    /**
     * @var string
     */
    protected string $classNamespace = '';

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $desc = $this->getDescription();
        if ($desc === '') {
            $this->setDescription(
                sprintf(
                    $this->translate('[%s] Generate %s'),
                    $this->command,
                    ucfirst($this->type)
                )
            );
        }
    }

    protected function configure()
    {
        parent::configure();
        $this->classNamespace = trim($this->classNamespace);
        $translate = sprintf(
            $this->translate('The %s command generates a blank %s class.'),
            '<info>%command.name%</info>',
            $this->type
        );
        $this
            ->addOption(
                'name',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf(
                    $this->translate('Name to use for the %s'),
                    strtolower($this->type)
                )
            )
             ->addOption(
                 'namespace',
                 null,
                 InputOption::VALUE_OPTIONAL,
                 sprintf(
                     $this->translate('The namespace to use for class name %s'),
                     strtolower($this->type)
                 )
             )
             ->setHelp(<<<EOT
$translate

    <info>%command.full_name%</info>

EOT
             );
    }

    public function doExecute(SymfonyStyle $symfonyStyle, InputInterface $input, OutputInterface $output): int
    {
        $isQuiet = $this->isQuiet;
        if ($this->classNamespace === '') {
            $namespace = $input->hasOption('namespace')
                ? $input->getOption('namespace')
                : null;
            if ($namespace && !Validator::isValidNamespace($namespace)) {
                throw new RuntimeException(
                    sprintf(
                        $this->translate('Namespace `%s` is invalid.'),
                        $namespace
                    )
                );
            }
        } else {
            $namespace = $this->classNamespace;
        }

        $namespace = is_string($namespace) ? $namespace : '';
        $namespace = trim($namespace, '\\');
        $name = $input->getOption('name');
        $name = is_string($name)
                && trim($name) !== ''
            ? $name
            : null;
        $name = $name ? ucwords($name, '\\') : null;
        $namespace = $namespace ? ucwords($namespace, '\\') : $namespace;
        $baseNamespace = $namespace;
        if (!$isQuiet) {
            $questionNameSpace      = new Question(
                $this->translate('Please Insert Namespace (Empty To Ignore)')
            );
            $questionNameSpaceValid = new Question(
                $this->translate('Please Insert Valid Namespace (Empty To Ignore)')
            );
            $questionName      = new Question(
                sprintf(
                    $this->translate('Please Insert %s Name'),
                    $this->type
                )
            );
            $questionNameValid = new Question(
                sprintf(
                    $this->translate('Please Insert Valid %s Name'),
                    $this->type
                )
            );
            if ($this->classNamespace === '') {
                $c = 0;
                do {
                    $question  = $c++ === 0
                        ? $symfonyStyle->askQuestion($questionNameSpace)
                        : $symfonyStyle->askQuestion($questionNameSpaceValid);
                    $namespace = $namespace !== '' && Validator::isValidNamespace($question)
                        ? trim($question, '\\')
                        : false;
                } while ($namespace === false);
            }
            $c = 0;
            if ($name !== null) {
                $name = ucwords($name, '\\');
                $namespace = $namespace ? ucwords($namespace, '\\') : $namespace;
                $fullClassName = $namespace ? "$namespace\\" : '';
                $fullClassName .= $name;
                $namespace = Validator::getNamespace($fullClassName);
                $name      = Validator::getClassBaseName($fullClassName);
                $ready = false;
                if (! $name || ($ready = $this->isReadyForWriting(
                    $namespace,
                    $name,
                    $fullClassName,
                    $symfonyStyle
                )) !== true) {
                    $symfonyStyle->writeln(
                        sprintf(
                            '<fg=red>%s</>',
                            is_string($ready) ? $ready : sprintf(
                                $this->translate(
                                    'Class Name %s exist or not valid for repository.'
                                ),
                                $name
                            )
                        )
                    );
                    $name = null;
                }
            }

            while ($name === null) {
                $question  = $c++ === 0
                    ? $symfonyStyle->askQuestion($questionName)
                    : $symfonyStyle->askQuestion($questionNameValid);
                $name = $question && preg_replace('~[\s\\\]~', '', $question) !== ''
                        && Validator::isValidClassName($question)
                    ? ltrim($question, '\\')
                    : null;
                $fullClassName = $name ? "$baseNamespace\\$name" : '';
                if ($name) {
                    $name = ucwords($name, '\\');
                    $fullClassName = "$baseNamespace\\$name";
                    $namespace = Validator::getNamespace($fullClassName);
                    $name      = Validator::getClassBaseName($fullClassName);
                }

                if ($name !== null && ($ready = $this->isReadyForWriting(
                    $namespace,
                    $name,
                    $fullClassName,
                    $symfonyStyle
                )) !== true) {
                    $symfonyStyle->writeln(
                        sprintf(
                            '<fg=red>%s</>',
                            is_string($ready) ? $ready : sprintf(
                                $this->translate(
                                    'Class Name %s exist or not valid for repository.'
                                ),
                                $name
                            )
                        )
                    );
                    $name = null;
                }
            }
        }

        if (!is_string($name) || !$name || trim($name) === '') {
            throw new RuntimeException(
                sprintf(
                    $this->translate(
                        '%s name could not be empty.'
                    ),
                    ucwords($this->type)
                )
            );
        }

        if ($isQuiet) {
            $name = ucwords($name, '\\');
            $namespace = $namespace ? ucwords($namespace, '\\') : $namespace;
            $fullClassName = $namespace ? "$namespace\\" : '';
            $fullClassName .= $name;
            $namespace = Validator::getNamespace($fullClassName);
            $name      = Validator::getClassBaseName($fullClassName);
            if (($ready = $this->isReadyForWriting(
                $namespace,
                $name,
                $fullClassName,
                $symfonyStyle
            )) !== true) {
                $symfonyStyle->writeln(
                    sprintf(
                        '<fg=red>%s</>',
                        is_string($ready) ? $ready : sprintf(
                            $this->translate(
                                'Class Name %s exist or not valid for repository.'
                            ),
                            $name
                        )
                    )
                );
                return self::FAILURE;
            }
        }

        return $this->doGeneration($namespace, $name, $symfonyStyle);
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param string $fullClassName
     * @param SymfonyStyle $symfonyStyle
     *
     * @return bool|string
     * @noinspection PhpUnusedParameterInspection
     */
    protected function isReadyForWriting(
        string $namespace,
        string $name,
        string $fullClassName,
        SymfonyStyle $symfonyStyle
    ) : bool|string {
        return true;
    }

    /**
     * Do command generation
     *
     * @param string $namespace
     * @param string $name
     * @param SymfonyStyle $symfonyStyle
     *
     * @return int
     */
    abstract protected function doGeneration(string $namespace, string $name, SymfonyStyle $symfonyStyle): int;
}
