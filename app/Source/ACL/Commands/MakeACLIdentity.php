<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL\Commands;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\MakeClassCommand;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Time;

class MakeACLIdentity extends MakeClassCommand
{
    protected string $command = 'acl:identity';
    protected string $type = 'identity acl';
    protected string $accessDirectory = '';
    protected string $classNamespace = 'TrayDigita\\Streak\\ACL\\Identity';

    /**
     * Configure command
     */
    protected function configure()
    {
        parent::configure();
        $ds = DIRECTORY_SEPARATOR;
        $appDir                   = Consolidation::appDirectory();
        $this->accessDirectory = "$appDir{$ds}ACL{$ds}Identity";
    }

    /**
     * @param SymfonyStyle $symfonyStyle
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function doExecute(SymfonyStyle $symfonyStyle, InputInterface $input, OutputInterface $output): int
    {
        $isQuiet = $this->isQuiet;
        $name = $input->getOption('name');
        $name = is_string($name)
                && trim($name) !== ''
            ? $name
            : null;
        $name = $name ? ucwords($name, '\\') : null;
        $namespace = $name ? "$this->classNamespace" : '';
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
                $namespace = "$this->classNamespace";
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
                    $namespace = "$this->classNamespace";
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
            $namespace = "$this->classNamespace";
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

    /**
     * @param string $namespace
     * @param string $name
     * @param string $fullClassName
     * @param SymfonyStyle $symfonyStyle
     *
     * @return bool|string
     */
    protected function isReadyForWriting(
        string $namespace,
        string $name,
        string $fullClassName,
        SymfonyStyle $symfonyStyle
    ): bool|string {
        if (!is_dir($this->accessDirectory) && is_writable(dirname($this->accessDirectory))) {
            mkdir($this->accessDirectory, 0755, true);
        }
        $subClass = substr($namespace, strlen($this->classNamespace));
        if (!is_dir($this->accessDirectory)) {
            throw new RuntimeException(
                $this->translate('Acl directory is not exist.')
            );
        }
        if (!empty($subClass)) {
            $this->isYes = false;
            return $this->translate(
                'Acl could not contain nested class.',
            );
        }
        $file = "$name.php";
        $fileName = $this->accessDirectory . "/$file";
        if (file_exists($fileName)) {
            $this->isYes = false;
            return sprintf(
                $this->translate('File %s already exists.'),
                $file
            );
        }
        return true;
    }

    /**
     * @param string $namespace
     * @param string $name
     * @param SymfonyStyle $symfonyStyle
     *
     * @return int
     */
    protected function doGeneration(string $namespace, string $name, SymfonyStyle $symfonyStyle): int
    {
        $fullClassName = $namespace ? "$namespace\\" : '';
        $fullClassName .= $name;

        $subClass = substr($namespace, strlen($this->classNamespace));
        $subClass = ltrim($subClass, '\\');
        $file = str_replace('\\', '/', $subClass);
        $file = $file ? "$file/" : '';
        $file .= "$name.php";
        $fileName = $this->accessDirectory . "/$file";
        $directory = dirname($fileName);
        if (!$this->isQuiet) {
            $namespaceText = $this->translate('Namespace');
            $baseClassText = $this->translate('Base class name');
            $classNameText = $this->translate('Class name');
            $directoryText = $this->translate('ACL Identity directory');
            $fileText      = $this->translate('ACL file');
            $symfonyStyle->writeln(<<<CLI
------------------------------------------------------

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

        $date = $this->getContainer(Time::class);
        $date = $date->getCurrentUTCTime()->format('Y-m-d H:i:s e');
        $lowerName = strtolower($name);
        $string = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use TrayDigita\Streak\Source\ACL\Abstracts\AbstractIdentity;
use TrayDigita\Streak\Source\Container;

/**
 * Identity ACL $name
 *
 * @generated $date
 */
class $name extends AbstractIdentity
{
   /**
    * ACL Access Identity
    * @param string
    */
    public string \$id = '$lowerName';

    /**
     * Call after construct
     *
     * @param Container \$container
     */
    protected function afterConstruct(Container \$container)
    {
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
