<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Models\Factory;

use DateTime;
use DateTimeInterface;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\ConversionException;
use RuntimeException;
use TrayDigita\Streak\Source\ACL\UserControl;
use TrayDigita\Streak\Source\Database\Abstracts\Model;
use TrayDigita\Streak\Source\Helper\Generator\RandomString;
use TrayDigita\Streak\Source\Models\Schema\UsersSchema;
use TrayDigita\Streak\Source\Traits\PasswordHashed;

/**
 * @property-read int $id
 * @property-read string $username
 * @property-read string $email
 * @property-read string $password
 * @property-read string $first_name
 * @property-read ?string $last_name
 * @property-read DateTimeInterface|DateTime $created_at
 * @property-read DateTimeInterface|DateTime $updated_at
 */
class Users extends Model
{
    use UsersSchema,
        PasswordHashed;

    /**
     * @var string
     */
    protected string $tableName = 'users';

    /**
     * @var ?UserControl
     */
    private ?UserControl $userControl = null;

    /**
     * @param string $name
     * @param $value
     *
     * @return mixed
     * @throws Exception
     * @throws ConversionException
     */
    protected function filterDatabaseValue(string $name, $value): mixed
    {
        if ($name === 'password') {
            $value = !$value ? RandomString::char() : $value;
            $value = !is_string($value) ? serialize($value) : $value;
            if ($this->passwordNeedRehash($value)) {
                $value = $this->hashPassword($value);
            }
        }

        return parent::filterDatabaseValue($name, $value);
    }

    /**
     * @param string $password
     *
     * @return bool
     */
    public function isValidPassword(string $password): bool
    {
        $pass = $this->getValueData('password')?:null;
        return $pass && $this->verifyPassword($password, $pass);
    }

    /**
     * @throws Exception
     */
    public function fromMetaUsers(UserMeta $metaUsers) : static
    {
        return static::find(['id' => $metaUsers->user_id?->id]);
    }

    /**
     * @return ?UserControl
     * @throws RuntimeException
     */
    public function getUserControl(): ?UserControl
    {
        if ($this->userControl) {
            return $this->userControl;
        }

        if (!$this->isFetched()) {
            throw new RuntimeException(
                $this->translate('Users model has not been fetched yet.')
            );
        }

        $this->userControl = new UserControl(
            $this->id,
            $this->username,
            $this->email,
            $this->password
        );

        return $this->userControl;
    }
}
