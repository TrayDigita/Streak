<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source;

use DateTimeImmutable;
use DateTimeZone;
use TrayDigita\Streak\Source\Abstracts\AbstractContainerization;

class Time extends AbstractContainerization
{
    /**
     * @var DateTimeImmutable
     */
    public readonly DateTimeImmutable $currentLocalTime;

    /**
     * @var DateTimeImmutable
     */
    public readonly DateTimeImmutable $currentUTCTime;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->currentLocalTime = $this->newDateTime();
        $this->currentUTCTime   = $this->currentLocalTime->setTimezone(new DateTimeZone('UTC'));
    }

    public function newDateTimeUTC() : DateTimeImmutable
    {
        return $this
            ->newDateTime()
            ->setTimezone($this->currentUTCTime->getTimezone());
    }

    public function newDateTime() : DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    /**
     * @return DateTimeImmutable
     */
    public function getCurrentLocalTime(): DateTimeImmutable
    {
        return $this->currentLocalTime;
    }

    /**
     * @return DateTimeImmutable|false
     */
    public function getCurrentUTCTime(): DateTimeImmutable|bool
    {
        return $this->currentUTCTime;
    }

    /**
     * @param DateTimeZone $dateTimeZone
     *
     * @return DateTimeImmutable
     */
    public function toTimeZone(DateTimeZone $dateTimeZone) : DateTimeImmutable
    {
        return $this->currentUTCTime->setTimezone($dateTimeZone);
    }

    private static ?array $listsAbbrZoneOffset = null;

    /**
     * @param string|int $timeZone eg: +07:00, -07:00
     * @param bool $returnDateTime
     *
     * @return array|DateTimeZone|null
     */
    public static function getTimeZoneFromHourAdditionsToAbbreviations(
        string|int $timeZone,
        bool $returnDateTime = false
    ) :array|DateTimeZone|null {
        if (is_int($timeZone) || (is_numeric($timeZone) && !str_contains($timeZone, '.'))) {
            $total = (int) $timeZone;
        } else {
            $timeZone = trim($timeZone);
            $timeZone = str_replace('.', ':', $timeZone);
            preg_match(
                '~(?P<identity>[+\-])?(?P<hour>[0-9]{1,2}):(?P<minutes>[0-5][0-9]?)(?P<second>[:][0-9]+)?$~',
                $timeZone,
                $match
            );
            if (empty($match)) {
                return null;
            }
            $hour    = intval($match['hour']) * 3600;
            $minutes = intval($match['minutes']) * 60;
            $total   = $hour + $minutes;
            $total   = ($match['identity'] ?? null) === '-' ? -$total : $total;
        }

        if (self::$listsAbbrZoneOffset === null) {
            foreach (DateTimeZone::listAbbreviations() as $areaName => $abbreviation) {
                foreach ($abbreviation as $item) {
                    if (empty($item['timezone_id'])) {
                        continue;
                    }
                    $item['abbr']                                 = $areaName;
                    self::$listsAbbrZoneOffset[$item['offset']][] = $item;
                }
            }
        }
        $data = self::$listsAbbrZoneOffset[$total] ?? null;
        if (empty($data)) {
            return null;
        }
        if (!$returnDateTime) {
            return $data['list'];
        }
        $reset = reset($data);
        return new DateTimeZone($reset['timezone_id']);
    }
}
