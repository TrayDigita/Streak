<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Consolidation
{
    public static function callbackReduceError(
        callable $callback,
        &$errNo = null,
        &$errStr = null,
        &$errFile = null,
        &$errline = null,
        &$errcontext = null
    ) {
        set_error_handler(function (
            $no,
            $str,
            $file,
            $line,
            $c
        ) use (
            &$errNo,
            &$errStr,
            &$errFile,
            &$errline,
            &$errcontext
        ) {
            $errNo = $no;
            $errStr = $str;
            $errFile = $file;
            $errline = $line;
            $errcontext = $c;
        });
        $result = $callback();
        restore_error_handler();
        return $result;
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     *
     * @return SymfonyStyle
     */
    public static function createSymfonyConsoleOutput(
        ?InputInterface $input = null,
        ?OutputInterface $output = null
    ) : SymfonyStyle {
        $input = $input??new ArgvInput();
        $output = $output??StreamCreator::createStreamOutput(null, true);
        return new SymfonyStyle(
            $input,
            $output
        );
    }

    /**
     * @param string $className
     *
     * @return string
     */
    public static function getNameSpace(string $className) : string
    {
        $className = ltrim($className, '\\');
        return preg_replace('~^(.+)?[\\\][^\\\]+$~', '$1', $className);
    }

    /**
     * @param string $fullClassName
     *
     * @return string
     */
    public static function getBaseClassName(string $fullClassName) : string
    {
        $fullClassName = ltrim($fullClassName, '\\');
        return preg_replace('~^(?:.+[\\\])?([^\\\]+)$~', '$1', $fullClassName);
    }
}
