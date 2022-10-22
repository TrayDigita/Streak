<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\ACL;

use JetBrains\PhpStorm\Pure;
use SplObjectStorage;
use TrayDigita\Streak\Source\ACL\Interfaces\IdentityInterface;

abstract class AbstractIdentity implements IdentityInterface
{
    /**
     * @var string identity id
     */
    protected string $id = '';

    /**
     * @var string identity name
     */
    protected string $name = '';

    /**
     * @var SplObjectStorage
     * @uses IdentityInterface[]
     */
    private SplObjectStorage $access;

    #[Pure] public function __construct()
    {
        $this->access = new SplObjectStorage();
        $this->id = $this->id?:get_class($this);
        $this->name = $this->name?:ucwords($this->id);
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    public function remove(AccessInterface|string $access): static
    {
        if (is_string($access)) {
            $this->access->rewind();
            while ($this->access->valid()) {
                $current = $this->access->current();
                if ($this->access->getInfo() === $access) {
                    unset($this->access[$current]);
                    break;
                }
            }
        }

        unset($this->access[$access]);
        return $this;
    }

    /**
     * When the access is object AccessInterface check the strict
     *
     * @param AccessInterface|string $access
     *
     * @return bool
     */
    public function has(AccessInterface|string $access): bool
    {
        if (is_string($access)) {
            $this->access->rewind();
            while ($this->access->valid()) {
                if ($this->access->getInfo() === $access) {
                    return true;
                }
            }
            return false;
        }
        return $this->access->contains($access);
    }

    public function add(AccessInterface $access): static
    {
        unset($this->access[$access]);
        $this->access->attach($access, $access->getId());
        return $this;
    }

    public function get(AccessInterface|string $access): ?AccessInterface
    {
        if (is_string($access)) {
            $this->access->rewind();
            while ($this->access->valid()) {
                /**
                 * @var AccessInterface $current
                 */
                $current = $this->access->current();
                if ($this->access->getInfo() === $access) {
                    return $current;
                }
            }
        }
        return $this->access[$access]??null;
    }

    public function permit(AccessInterface|string $access): bool
    {
        return $this->has($access);
    }

    #[Pure] final public function __toString(): string
    {
        return $this->getId();
    }

    #[Pure] public function __get(string $name)
    {
        return match ($name) {
            'id' => $this->getId(),
            'name' => $this->getName(),
            default => null
        };
    }
}
