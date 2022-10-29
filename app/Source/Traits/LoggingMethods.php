<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

trait LoggingMethods
{
    public function logDebug(string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->debug($message, $context);
    }

    public function logError(string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->error($message, $context);
    }

    public function logEmergency(string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->emergency($message, $context);
    }

    public function logWarning(string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->warning($message, $context);
    }

    public function logInfo(string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->info($message, $context);
    }

    public function logAlert(string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->alert($message, $context);
    }

    public function logCritical(string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->critical($message, $context);
    }

    public function logNotice(string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->notice($message, $context);
    }

    public function log($level, string|Stringable $message, array $context = [])
    {
        $this?->getContainer(LoggerInterface::class)->log($level, $message, $context);
    }

    public function logErrorException(Throwable $exception, array $context = [])
    {
        $this->logError($exception->getMessage(), ['exception' => $exception, ...$context]);
    }

    public function logWarningException(Throwable $exception, array $context = [])
    {
        $this->logWarning($exception->getMessage(), ['exception' => $exception, ...$context]);
    }

    public function logDebugException(Throwable $exception, array $context = [])
    {
        $this->logDebug($exception->getMessage(), ['exception' => $exception, ...$context]);
    }

    public function logInfoException(Throwable $exception, array $context = [])
    {
        $this->logInfo($exception->getMessage(), ['exception' => $exception, ...$context]);
    }
}
