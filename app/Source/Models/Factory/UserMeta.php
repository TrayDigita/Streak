<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Models\Factory;

use Doctrine\DBAL\Exception;
use TrayDigita\Streak\Source\Database\Abstracts\Model;
use TrayDigita\Streak\Source\Models\Schema\UserMetaSchema;

/**
 * @property-read Users $user_id
 * @property-read string $meta_name
 * @property-read mixed $meta_value
 */
class UserMeta extends Model
{
    use UserMetaSchema;

    /**
     * @var string
     */
    protected string $tableName = 'user_meta';

    /**
     * @throws Exception
     */
    public static function findFromUsers(Users $user) : static
    {
        return static::find(['user_id' => $user]);
    }
}
