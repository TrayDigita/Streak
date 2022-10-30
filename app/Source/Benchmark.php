<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;
use TrayDigita\Streak\Source\Helper\Data\TimeStorage;

final class Benchmark extends AbstractContainerization implements JsonSerializable
{
    /**
     * @var array<string, array<TimeStorage>>
     */
    protected array $records = [];

    /**
     * @var array<string, float>
     */
    protected array $temporaryRecords = [];

    /**
     * @var ?string
     */
    protected ?string $lastRecord = null;

    /**
     * @var float
     */
    public readonly float $startTime;

    #[Pure] public function __construct(Container $container)
    {
        $this->startTime = microtime(true);
        parent::__construct($container);
    }

    /**
     * @return float
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }


    public function start(string $name)
    {
        $this->temporaryRecords[$name] = microtime(true);
        $this->lastRecord = $name;
    }

    public function stop(?string $name = null)
    {
        $name = $name??$this->lastRecord;
        if ($name === null || !isset($this->temporaryRecords[$name])) {
            return;
        }

        $this->records[$name][] = new TimeStorage(
            $this,
            $name,
            $this->temporaryRecords[$name],
            microtime(true)
        );
    }

    public function addStop(string $name) : void
    {
        $this->records[$name][] = new TimeStorage(
            $this,
            $name,
            $this->getStartTime(),
            microtime(true)
        );
    }

    public function stopFrom(string $from, string $name)
    {
        if (!isset($this->temporaryRecords[$from])) {
            return;
        }

        $this->records[$name][] = new TimeStorage(
            $this,
            $name,
            $this->temporaryRecords[$from],
            microtime(true)
        );
    }

    public function deduplicate(string $name)
    {
        if (!isset($this->records[$name])
            || count($this->records[$name]) < 2
        ) {
            return;
        }
        $this->records[$name] = [end($this->records[$name])];
    }

    public function remove(string $name)
    {
        unset($this->records[$name]);
    }

    /**
     * @return array<string, array<TimeStorage>>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @param string $name
     *
     * @return ?TimeStorage[]
     */
    public function get(string $name) : ?array
    {
        return $this->records[$name]??null;
    }

    public function all() : array
    {
        return $this->records;
    }

    public function keys() : array
    {
        return array_keys($this->records);
    }

    #[Pure] public function jsonSerialize() : array
    {
        return $this->all();
    }

    public function __destruct()
    {
        $this->temporaryRecords = [];
        $this->records = [];
    }
}
