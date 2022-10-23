<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Database\Commands;

use RuntimeException;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\MakeClassCommand;
use TrayDigita\Streak\Source\Database\Abstracts\Model;
use TrayDigita\Streak\Source\Events;
use TrayDigita\Streak\Source\StoragePath;
use TrayDigita\Streak\Source\Time;

class MakeModel extends MakeClassCommand
{
    protected string $command = 'model';
    protected string $type = 'model';
    protected string $modelDirectory = '';
    protected string $classNamespace = 'TrayDigita\\Streak\\Model';

    protected function configure()
    {
        parent::configure();
        $app = $this->getContainer(StoragePath::class)->getAppDirectory();
        $this->modelDirectory = "$app/Model";
    }

    protected function isReadyForWriting(
        string $namespace,
        string $name,
        string $fullClassName,
        SymfonyStyle $symfonyStyle
    ): bool|string {
        $subClass = substr($namespace, strlen($this->classNamespace));
        if (!is_dir($this->modelDirectory)) {
            throw new RuntimeException(
                $this->translate('Model directory is not exist.')
            );
        }

        $subClass = ltrim($subClass, '\\');
        $file = str_replace('\\', '/', $subClass);
        $file = $file ? "$file/" : '';
        $file .= "$name.php";
        $fileName = $this->modelDirectory . "/$file";
        if (file_exists($fileName)) {
            $this->isYes = false;
            return sprintf(
                $this->translate('File %s already exists.'),
                $file
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
        $fileName = $this->modelDirectory . "/$file";
        $directory = dirname($fileName);
        if (!$this->isQuiet) {
            $namespaceText = $this->translate('Namespace');
            $baseClassText = $this->translate('Base class name');
            $classNameText = $this->translate('Class name');
            $directoryText = $this->translate('Model directory');
            $fileText      = $this->translate('Model file');
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
        $date = $date->getCurrentTimeUTC()->format('Y-m-d H:i:s e');
        $basename = $subClass ? "$subClass\\$name" : $name;
        $lower_class_name = preg_replace('~([A-Z])~', '_$1', $name);
        $lower_class_name = trim(preg_replace('~[_]+~', '_', $lower_class_name), '_');
        $lower_name = strtolower(str_replace('\\', '_', $subClass));
        $lower_name = $lower_name !== '' ? "{$lower_name}_$lower_class_name" : $lower_class_name;
        $lower_name = strtolower($lower_name);
        $string = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use TrayDigita\Streak\Source\Database\Abstracts\Model;

/**
 * Model $basename
 *
 * @generated $date
 */
class $name extends Model
{
    /**
     * The table name, change with certain table name
     * @param string \$tableName
     */
    protected string \$tableName = '$lower_name';

    /**
     * Table structures
     *
     * @var array
     */
    protected array \$tableStructureData = [];

    /**
     * Indexes on table
     *
     * @var array
     */
    protected array \$tableStructureIndexes = [];

    /**
     * @inheritDoc
     */
    public function filterValue(string \$name, \$value) : mixed
    {
        // example to add events
        \$value = parent::filterValue(\$name, \$value);
        return \$this
            ->eventDispatch(
                'Model:$basename:filterValue',
                \$value,
                \$this
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
