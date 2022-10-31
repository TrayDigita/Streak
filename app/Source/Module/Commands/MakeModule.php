<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Module\Commands;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\MakeClassCommand;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Module\Collector;
use TrayDigita\Streak\Source\Time;

class MakeModule extends MakeClassCommand
{
    protected string $command = 'module';
    protected string $type = 'module';
    protected string $moduleDirectory = '';
    protected string $classNamespace = 'TrayDigita\\Streak\\Module';

    protected function configure()
    {
        parent::configure();
        $collector = $this->getContainer(Collector::class);
        $namespace = $collector->getNamespaces();
        $this->classNamespace = reset($namespace);
        $this->moduleDirectory = $collector->getModuleDirectory();
    }

    public function doExecute(SymfonyStyle $symfonyStyle, InputInterface $input, OutputInterface $output): int
    {
        $isQuiet = $this->isQuiet;
        $name = $input->getOption('name');
        $name = is_string($name)
                && trim($name) !== ''
            ? $name
            : null;
        $name = $name ? ucwords($name, '\\') : null;
        $namespace = $name ? "$this->classNamespace\\$name" : '';
        if (!$isQuiet) {
            $questionName      = new Question(
                sprintf(
                    $this->translate('Please Insert %s Name'),
                    ucwords($this->type)
                )
            );
            $questionNameValid = new Question(
                sprintf(
                    $this->translate('Please Insert Valid %s Name'),
                    ucwords($this->type)
                )
            );
            $c = 0;
            if ($name !== null) {
                $name = ucwords($name, '\\');
                $namespace = "$this->classNamespace\\$name";
                $fullClassName = "$namespace\\$name";
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
                    $name = null;
                }
            }

            while ($name === null) {
                $namespace = null;
                $fullClassName = null;
                $question  = $c++ === 0
                    ? $symfonyStyle->askQuestion($questionName)
                    : $symfonyStyle->askQuestion($questionNameValid);
                $name = $question && preg_replace('~[\s\\\]~', '', $question) !== ''
                        && Validator::isValidClassName($question)
                    ? ltrim($question, '\\')
                    : null;
                if ($name) {
                    $name = ucwords($name, '\\');
                    $namespace = "$this->classNamespace\\$name";
                    $fullClassName = "$namespace\\$name";
                }
                $ready = false;
                if (!$name || ! $namespace || !$fullClassName || ($ready = $this->isReadyForWriting(
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
            $namespace = "$this->classNamespace\\$name";
            $fullClassName = "$namespace\\$name";
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

    protected function isReadyForWriting(
        string $namespace,
        string $name,
        string $fullClassName,
        SymfonyStyle $symfonyStyle
    ): bool|string {
        if (!is_dir($this->moduleDirectory) && is_writable(dirname($this->moduleDirectory))) {
            mkdir($this->moduleDirectory, 0755, true);
        }

        if (!is_dir($this->moduleDirectory)) {
            throw new RuntimeException(
                $this->translate('Module directory is not exist.')
            );
        }

        $newNamespace = "$this->classNamespace\\$name";
        $subClass = substr($namespace, strlen($newNamespace));
        if (!empty($subClass)) {
            $this->isYes = false;
            return $this->translate(
                'Module could not contain nested class.',
            );
        }
        $dir = "$this->moduleDirectory/$name";
        if (file_exists($dir)) {
            $this->isYes = false;
            return sprintf(
                $this->translate('Directory %s already exists.'),
                $dir
            );
        }

        return true;
    }

    protected function doGeneration(string $namespace, string $name, SymfonyStyle $symfonyStyle): int
    {
        $fullClassName = $namespace ? "$namespace\\" : '';
        $fullClassName .= $name;

        $subClass = substr($namespace, strlen($this->classNamespace));
        $subClass = ltrim($subClass, '\\');
        $file = str_replace('\\', '/', $subClass);
        $file = $file ? "$file/" : '';
        $file .= "$name.php";
        $fileName = $this->moduleDirectory . "/$file";
        $directory = dirname($fileName);
        if (!$this->isQuiet) {
            $moduleText    = $this->translate('Module name');
            $namespaceText = $this->translate('Namespace');
            $baseClassText = $this->translate('Base class name');
            $classNameText = $this->translate('Class name');
            $directoryText = $this->translate('Module directory');
            $fileText      = $this->translate('Module file');
            $symfonyStyle->writeln(<<<CLI
------------------------------------------------------

- <fg=blue>$moduleText</> : $name
- <fg=blue>$namespaceText</> : $namespace
- <fg=blue>$baseClassText</> : $name
- <fg=blue>$classNameText</> : $fullClassName
- <fg=blue>$directoryText</> : $directory
- <fg=blue>$fileText</> : $name.php

------------------------------------------------------
CLI
            );
            if (!$this->isYes) {
                $question = new Question(
                    $this->translate('Are you sure [Y/n]'),
                    'Y'
                );
                do {
                    $accept = $symfonyStyle->askQuestion($question);
                } while (!preg_match('~(y(?:es)?|no?)~i', (string)$accept));

                if (!$accept) {
                    $symfonyStyle->writeln(
                        '<fg=red>[CANCELLED]</>'
                    );

                    return 0;
                }
            }
        }
        $moduleDescription = sprintf(
            $this->translate('Module %s.'),
            $name
        );
        $moduleDescription = preg_quote($moduleDescription, "'");
        $date = $this->getContainer(Time::class);
        $date = $date->getCurrentUTCTime()->format('Y-m-d H:i:s e');
        $string = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use TrayDigita\Streak\Source\Module\Abstracts\AbstractModule;

/**
 * Module $name
 *
 * @generated $date
 */
class $name extends AbstractModule
{
    /**
     * Module version
     * @var string
     */
    protected string \$version = '1.0.0';

    /**
     * Module name
     * @var string
     */
    protected string \$name = '$name';

    /**
     * Module description
     * @var string
     */
    protected string \$description = '$moduleDescription';

    /**
     * Set module direct initialize after load
     * @var bool
     */
    protected bool \$activateModule = true;

    /**
     * @var bool enable autoload
     */
    protected bool \$addAutoload = true;

    /**
     * Module priority
     *
     * @return int
     */
    public static function thePriority(): int
    {
        return 10;
    }

    /**
     * Doing the process after module init
     * @see initModule()
     */
    protected function afterInit()
    {
        // do after construct
    }
}

PHP;

        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        if (!is_writable($directory)) {
            throw new RuntimeException(
                sprintf(
                    $this->translate(
                        'Directory %s is not writable.'
                    ),
                    $directory
                )
            );
        }

        if (file_exists($file)) {
            throw new RuntimeException(
                sprintf(
                    $this->translate(
                        'File %s is exists.'
                    ),
                    $file
                )
            );
        }

        return file_put_contents($fileName, $string) !== false ? self::SUCCESS : self::FAILURE;
    }
}
