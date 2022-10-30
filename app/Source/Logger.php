<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use DateTimeZone;
use Monolog\DateTimeImmutable;
use Monolog\Level;
use Monolog\Logger as Monolog;
use TrayDigita\Streak\Source\Interfaces\ContainerizeInterface;
use TrayDigita\Streak\Source\Records\Collections;
use TrayDigita\Streak\Source\Traits\Containerize;
use TrayDigita\Streak\Source\Traits\EventsMethods;

class Logger extends Monolog implements ContainerizeInterface
{
    use Containerize,
        EventsMethods;

    /**
     * @var Container
     */
    public readonly Container $container;

    /**
     * @var bool
     */
    public readonly bool $logging;

    /**
     * @var bool
     */
    protected ?bool $loggingEnabled = null;

    /**
     * @param Container $container
     * @param string|null $logName
     * @param array $logHandlers
     * @param array $logProcessors
     * @param DateTimeZone|null $logTimezone
     */
    public function __construct(
        Container $container,
        ?string $logName = null,
        array $logHandlers = [],
        array $logProcessors = [],
        DateTimeZone $logTimezone = null
    ) {
        $this->container = $container;
        $logName = $logName??$container->get(Application::class)->uuid;
        $logging = $this->getContainer(Configurations::class)->get('logging');
        $this->logging = ($logging instanceof Collections ? $logging->get('enable') : true) === true;

        parent::__construct(
            $logName,
            $logHandlers,
            $logProcessors,
            $logTimezone
        );
    }

    /**
     * @inheritDoc
     */
    public function addRecord(
        int|Level $level,
        string $message,
        array $context = [],
        DateTimeImmutable $datetime = null
    ): bool {

        // call once
        if ($this->loggingEnabled === null) {
            $this->loggingEnabled = $this->eventDispatch('Logging:enable', $this->logging) === true;
        }
        if (!$this->loggingEnabled) {
            return false;
        }
        return parent::addRecord($level, $message, $context, $datetime);
    }
}
