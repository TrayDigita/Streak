<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Models;

use Doctrine\DBAL\Exception;
use TrayDigita\Streak\Source\Database\Abstracts\Model;
use TrayDigita\Streak\Source\Models\Schema\ActionSchedulersLogSchema;

class ActionSchedulersLog extends Model
{
    use ActionSchedulersLogSchema;

    /**
     * @var bool
     */
    protected bool $modelUsePrefix = true;

    /**
     * @var bool
     */
    protected bool $modelAutoPrefix = true;

    /**
     * @var string
     */
    protected string $tableName = 'action_schedulers_log';

    /**
     * @param ActionSchedulers $actionSchedulers
     *
     * @return bool|int
     * @throws Exception
     */
    public static function insertFromActionScheduler(ActionSchedulers $actionSchedulers): bool|int
    {
        if (!$actionSchedulers->isModelFetched()) {
            return false;
        }
        $obj = new static($actionSchedulers->instance);
        $params = [
            'callback' => $actionSchedulers->callback,
            'status' => $actionSchedulers->status,
            'message' => $actionSchedulers->message,
            'processed_time' => $actionSchedulers->processed_time,
            'last_execute'  => $actionSchedulers->last_execute,
            'created_at' => $obj->nowDateTime(),
        ];
        return $obj->insert($params);
    }
}
