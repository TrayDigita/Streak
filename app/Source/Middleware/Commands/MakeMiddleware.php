<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Middleware\Commands;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\MakeClassCommand;
use TrayDigita\Streak\Source\Middleware\Collector;
use TrayDigita\Streak\Source\Time;

class MakeMiddleware extends MakeClassCommand
{
    protected string $command = 'middleware';
    protected string $type = 'middleware';
    protected string $middlewareDirectory = '';
    protected string $classNamespace = 'TrayDigita\\Streak\\Middleware';
    protected function configure()
    {
        parent::configure();
        $collector = $this->getContainer(Collector::class);
        $namespace = $collector->getNamespaces();
        $this->classNamespace = reset($namespace);
        $this->middlewareDirectory = $collector->getMiddlewareDirectory();
    }

    protected function isReadyForWriting(
        string $namespace,
        string $name,
        string $fullClassName,
        SymfonyStyle $symfonyStyle
    ): bool|string {
        if (!is_dir($this->middlewareDirectory) && is_writable(dirname($this->middlewareDirectory))) {
            mkdir($this->middlewareDirectory, 0755, true);
        }
        $subClass = substr($namespace, strlen($this->classNamespace));
        if (!is_dir($this->middlewareDirectory)) {
            throw new RuntimeException(
                $this->translate('Middleware directory is not exist.')
            );
        }
        if (!empty($subClass)) {
            $this->isYes = false;
            return $this->translate(
                'Middleware could not contain nested class.',
            );
        }
        $file = "$name.php";
        $fileName = $this->middlewareDirectory . "/$file";
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
        $fileName = $this->middlewareDirectory . "/$file";
        $directory = dirname($fileName);
        if (!$this->isQuiet) {
            $namespaceText = $this->translate('Namespace');
            $baseClassText = $this->translate('Base class name');
            $classNameText = $this->translate('Class name');
            $directoryText = $this->translate('Middleware directory');
            $fileText      = $this->translate('Middleware file');
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
        $string = <<<PHP
<?php
declare(strict_types=1);

namespace $namespace;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use TrayDigita\Streak\Source\Middleware\Abstracts\AbstractMiddleware;

/**
 * Middleware $name
 *
 * @generated $date
 */
class $name extends AbstractMiddleware
{
    /**
     * Priority of middleware, the lowest value will be last execute
     *
     * @return int
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Process the middleware
     *
     * @param ServerRequestInterface \$request
     * @param RequestHandlerInterface \$handler
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface \$request,
        RequestHandlerInterface \$handler
    ): ResponseInterface {
        return \$handler->handle(\$request);
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
