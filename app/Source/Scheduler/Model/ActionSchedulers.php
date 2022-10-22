<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler\Model;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\TextType;
use ReflectionClass;
use Throwable;
use TrayDigita\Streak\Source\Database\Abstracts\Model;
use TrayDigita\Streak\Source\Scheduler\Abstracts\AbstractTask;

/**
 * @property-read string $callback
 * @property-read string $status
 * @property-read string $message
 * @property-read float|int $interval
 * @property-read float|null $processed_time
 * @property-read DateTime|DateTimeInterface $created_at
 * @property-read DateTime|DateTimeInterface $last_execute
 */
class ActionSchedulers extends Model
{
    const PROGRESS = 'progress';
    const SUCCESS = 'success';
    const PENDING = 'pending';
    const FAILURE = 'fail';
    const SKIPPED = 'skipped';
    const UNKNOWN = 'unknown';

    protected string $tableName = 'action_schedulers';
    /**
     * @var array<string, class-string<AbstractTask>>
     */
    private static array $callbackResource = [];

    /**
     * @var array|array[]
     */
    protected array $tableStructureData = [
        'callback' => [
            'type' => StringType::class,
            'options' => [
                'length' => 512,
                'notnull' => true,
                'comment' => 'Scheduler callback',
            ],
            'index' => 'callback',
            'unique' => 'callback',
            'primary' => true
        ],
        'status' => [
            'type' => StringType::class,
            'options' => [
                'length' => 120,
                'notnull' => true,
                'comment' => 'Scheduler status',
                'default' => 'pending'
            ],
            'index' => 'status'
        ],
        'message' => [
            'type' => TextType::class,
            'options' => [
                'length'  => 65535,
                'notnull' => true,
                'default' => '',
                'comment' => 'Scheduler message'
            ],
        ],
        'processed_time' => [
            'type' => FloatType::class,
            'options' => [
                'notnull' => false,
                'default' => null,
                'comment' => 'processed_time float'
            ],
            'index' => 'processed_time'
        ],
        'created_at' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => true,
                'default' => 'CURRENT_TIMESTAMP',
                'comment' => 'created_at time'
            ],
            'index' => 'created_at'
        ],
        'last_execute' => [
            'type' => DateTimeType::class,
            'options' => [
                'notnull' => false,
                'default' => "0000-00-00 00:00:00",
                'onUpdate' => 'current_timestamp',
                'comment' => 'last_execute time'
            ],
            'index' => 'last_execute'
        ],
    ];

    /**
     * @return ?class-string
     */
    public function getValidCallbackClass() : ?string
    {
        $callback = $this->callback;
        if (!$callback || !is_string($callback)) {
            return null;
        }
        $lower = strtolower(trim(trim($callback), '\\'));
        if (!isset(self::$callbackResource[$lower])) {
            self::$callbackResource[$lower] = false;
            try {
                $ref                            = new ReflectionClass($callback);
                if ($ref->isSubclassOf(AbstractTask::class)) {
                    self::$callbackResource[$lower] = $ref->getName();
                }
            } catch (Throwable) {
            }
        }
        return self::$callbackResource[$lower]?:null;
    }
}
