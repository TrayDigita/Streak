<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Models;

use DateTime;
use DateTimeInterface;
use ReflectionClass;
use Throwable;
use TrayDigita\Streak\Source\Database\Abstracts\Model;
use TrayDigita\Streak\Source\Models\Schema\ActionSchedulerSchema;
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
    use ActionSchedulerSchema;

    const PROGRESS = 'progress';
    const SUCCESS = 'success';
    const PENDING = 'pending';
    const FAILURE = 'fail';
    const SKIPPED = 'skipped';
    const UNKNOWN = 'unknown';

    /**
     * @var string
     */
    protected string $tableName = 'action_schedulers';

    /**
     * @var array<string, class-string<AbstractTask>>
     */
    private static array $callbackResource = [];

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
