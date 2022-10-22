<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Traits;

use Psr\Log\LoggerInterface;
use Stringable;

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
}
