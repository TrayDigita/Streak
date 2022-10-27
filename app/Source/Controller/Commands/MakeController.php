<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Controller\Commands;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\MakeClassCommand;
use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;
use TrayDigita\Streak\Source\Controller\Collector;
use TrayDigita\Streak\Source\Time;

class MakeController extends MakeClassCommand
{
    protected string $command = 'controller';
    protected string $type = 'controller';
    protected string $controllerDirectory = '';
    protected string $controllerNamespace = 'TrayDigita\\Streak\\Controller';
    protected int $maxDepth = 1;

    protected function configure()
    {
        parent::configure();
        $collector = $this->getContainer(Collector::class);
        $namespace = $collector->getNamespaces();
        $this->classNamespace = reset($namespace);
        $this->controllerNamespace = $this->classNamespace;
        $this->maxDepth = $collector->getMaxDepth();
        $this->controllerDirectory = $collector->getControllerDirectory();
    }

    protected function isReadyForWriting(
        string $namespace,
        string $name,
        string $fullClassName,
        SymfonyStyle $symfonyStyle
    ): bool|string {
        $subClass = substr($namespace, strlen($this->classNamespace));
        if (!is_dir($this->controllerDirectory)) {
            throw new RuntimeException(
                $this->translate('Controller directory is not exist.')
            );
        }

        $subClass = ltrim($subClass, '\\');
        $count = count(explode('\\', $subClass));
        if (($count+1) > $this->maxDepth) {
            $this->isYes = false;
            return sprintf(
                $this->translate(
                    'Max depth of class name is %d, you have %d depth.'
                ),
                $this->maxDepth+1,
                $count
            );
        }
        $file = str_replace('\\', '/', $subClass);
        $file = $file ? "$file/" : '';
        $file .= "$name.php";
        $fileName = $this->controllerDirectory . "/$file";
        if (file_exists($fileName)) {
            $this->isYes = false;
            return sprintf(
                $this->translate(
                    'File %s already exists.'
                ),
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
        $fileName = $this->controllerDirectory . "/$file";
        $directory = dirname($fileName);
        if (!$this->isQuiet) {
            $namespaceText = $this->translate('Namespace');
            $baseClassText = $this->translate('Base class name');
            $classNameText = $this->translate('Class name');
            $directoryText = $this->translate('Controller directory');
            $fileText      = $this->translate('Controller file');
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
        $basename = $subClass ? "$subClass\\$name" : $name;
        $lower_name = strtolower(str_replace('\\', '/', $subClass));
        /** @noinspection PhpUnnecessaryCurlyVarSyntaxInspection */
        $string = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use TrayDigita\Streak\Source\Controller\Abstracts\AbstractController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller $basename
 *
 * @generated $date
 */
class $name extends AbstractController
{
    /**
     * Set the controller priority
     *
     * @return int the priority
     */
    public static function thePriority() : int
    {
        //change the priority, sorted from small priority
        return 10;
    }

    /**
     * Set the controller group route pattern
     *
     * @return string the route group pattern
     */
    public function getGroupRoutePattern() : string
    {
        return parent::getGroupRoutePattern();
    }

    /**
     * Set the controller route pattern
     *
     * @return string the route pattern
     */
    public function getRoutePattern() : string
    {
        //change the route
        // match with (/$lower_name) & ($lower_name/)
        return '/{$lower_name}[/]';
    }

    /**
     * Do the routing process
     *
     * @param ServerRequestInterface \$request
     * @param ResponseInterface \$response
     * @param array \$params
     *
     * @return ResponseInterface
     */
    public function doRouting(
        ServerRequestInterface \$request,
        ResponseInterface \$response,
        array \$params = []
    ) : ResponseInterface {
        // do with the \$response
        return \$response;
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
