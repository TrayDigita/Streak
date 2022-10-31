<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler\Commands;

use RuntimeException;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\MakeClassCommand;
use TrayDigita\Streak\Source\Helper\Util\Consolidation;
use TrayDigita\Streak\Source\Time;

class MakeScheduler extends MakeClassCommand
{
    protected string $command = 'scheduler';
    protected string $type = 'scheduler';
    protected string $schedulerDirectory = '';
    protected string $classNamespace = 'TrayDigita\\Streak\\Scheduler';

    /**
     * Configure command
     */
    protected function configure()
    {
        parent::configure();
        $appDir = Consolidation::appDirectory();
        $this->schedulerDirectory = "$appDir". DIRECTORY_SEPARATOR . "Scheduler";
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
        if (!is_dir($this->schedulerDirectory) && is_writable(dirname($this->schedulerDirectory))) {
            mkdir($this->schedulerDirectory, 0755, true);
        }
        $subClass = substr($namespace, strlen($this->classNamespace));
        if (!is_dir($this->schedulerDirectory)) {
            throw new RuntimeException(
                $this->translate('Scheduler directory is not exist.')
            );
        }
        if (!empty($subClass)) {
            $this->isYes = false;
            return $this->translate(
                'Scheduler could not contain nested class.',
            );
        }
        $file = "$name.php";
        $fileName = $this->schedulerDirectory . "/$file";
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
        $fileName = $this->schedulerDirectory . "/$file";
        $directory = dirname($fileName);
        if (!$this->isQuiet) {
            $namespaceText = $this->translate('Namespace');
            $baseClassText = $this->translate('Base class name');
            $classNameText = $this->translate('Class name');
            $directoryText = $this->translate('Scheduler directory');
            $fileText      = $this->translate('Scheduler file');
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
        $fill = sprintf(
            $this->translate('Fill with response result of task of %s'),
            'Stringable'
        );
        $fill = preg_quote($fill, "'");
        $string = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use TrayDigita\Streak\Source\Scheduler\Abstracts\AbstractTask;
use TrayDigita\Streak\Source\Scheduler\TaskStatus;

/**
 * Scheduler $name
 *
 * @generated $date
 */
class $name extends AbstractTask
{
    /**
     * Set interval in seconds.
     * Less than 1 second will be skipped
     *
     * @var int in second
     */
    protected int \$interval = 0;

    /**
     * Process The Scheduler
     * @param array \$arguments
     * @return TaskStatus
     */
    protected function processTask(array \$arguments = []): TaskStatus
    {
        // do task here ....
        return self::createStatus(
            TaskStatus::SUCCESS,
            '$fill'
        );
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
