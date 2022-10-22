<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use DateTimeZone;
use Monolog\DateTimeImmutable;
use Monolog\Handler\PsrHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Throwable;
use TrayDigita\Streak\Source\Interfaces\ContainerizeInterface;
use TrayDigita\Streak\Source\Records\Collections;
use TrayDigita\Streak\Source\Traits\Containerize;
use TrayDigita\Streak\Source\Traits\EventsMethods;

class Logger extends Monolog implements ContainerizeInterface
{
    use Containerize,
        EventsMethods;

    private PsrHandler $psrHandler;

    public function __construct(
        private Container $container,
        ?string $logName = null,
        array $logHandlers = [],
        array $logProcessors = [],
        DateTimeZone $logTimezone = null
    ) {
        if (!$logName) {
            $appName = $container->get(Application::class)->getName();
            $logNamed = $this->eventDispatch('Logger:name', $appName);
            $logName = !is_string($logNamed) || trim($logNamed) === '' ? $appName : $logNamed;
        }
        $logging = $container->get(Configurations::class)->get('logging');
        $level   = $logging?->get('level') ?: Level::Warning;
        if (!$level instanceof Level) {
            if (is_string($level) || is_int($level)) {
                try {
                    $level = Monolog::toMonologLevel($level);
                } catch (Throwable) {
                    $level = Level::Warning;
                }
            }
        }

        // push psr handler
        $this->psrHandler = new PsrHandler($this, $level);
        array_push($logHandlers, $this->psrHandler);
        parent::__construct($logName, $logHandlers, $logProcessors, $logTimezone);
    }

    /**
     * @return PsrHandler
     */
    public function getPsrHandler() : PsrHandler
    {
        return $this->psrHandler;
    }

    public function addRecord(
        int|Level $level,
        string $message,
        array $context = [],
        DateTimeImmutable $datetime = null
    ): bool {
        $log = $this->getContainer(Configurations::class)->get('logging');
        $log = $log instanceof Collections ? $log->get('enable') : true;
        if ($log === false) {
            return true;
        }
        return parent::addRecord($level, $message, $context, $datetime); // TODO: Change the autogenerated stub
    }
}