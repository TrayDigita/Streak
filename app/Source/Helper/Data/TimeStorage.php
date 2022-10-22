<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Data;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonSerializable;
use Stringable;
use TrayDigita\Streak\Source\Json\EncodeDecode;
use TrayDigita\Streak\Source\Benchmark;

class TimeStorage implements JsonSerializable, Stringable
{
    /**
     * @var int
     */
    private int $memory;

    public function __construct(
        private Benchmark $time_record,
        private string $name,
        private float $start,
        private float $end
    ) {
        $this->memory = memory_get_usage(true);
    }

    /**
     * @return Benchmark
     */
    public function getTimeRecord(): Benchmark
    {
        return $this->time_record;
    }

    /**
     * @return int
     */
    public function getMemory(): int
    {
        return $this->memory;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return float
     */
    public function getStart(): float
    {
        return $this->start;
    }

    /**
     * @return float
     */
    public function getEnd() : float
    {
        return $this->end;
    }

    #[Pure] public function getEstimated() : float
    {
        return $this->getEnd() - $this->getStart();
    }

    #[Pure] #[ArrayShape(
        [
            'start' => "float",
            'end' => "float",
            'estimated' => "float",
            'memory' => "int"
        ]
    )] public function jsonSerialize() : array
    {
        return [
            'start' => $this->getStart(),
            'end' => $this->getEnd(),
            'estimated' => $this->getEstimated(),
            'memory' =>$this->getMemory(),
        ];
    }

    public function __toString() : string
    {
        return $this
            ->time_record
            ->getContainer(EncodeDecode::class)
            ->encode($this->jsonSerialize());
    }
}
