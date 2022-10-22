<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Database;

use ArrayIterator;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use IteratorAggregate;
use Traversable;
use TrayDigita\Streak\Source\Database\Abstracts\Model;

class ResultCollection implements IteratorAggregate
{
    protected ?array $resultSet = null;
    private $moveData;
    private $callbackInternalData;
    private Model $model;

    public function __construct(
        private Result $result,
        Model $model,
        callable $moveData,
        callable $callbackInternalData,
    ) {
        $this->model = clone $model;
        $this->model->resetQuery();
        $this->moveData = $moveData;
        $this->callbackInternalData = $callbackInternalData;
    }

    /**
     * @return false|Model
     * @throws Exception
     */
    public function fetch() : false|Model
    {
        if ($this->resultSet === null) {
            $this->resultSet = [];
        }
        $unique_id = $this->model->getUniqueId();
        $callback = $this->moveData;
        $callbackInternalData = $this->callbackInternalData;
        $row = $this->result->fetchAssociative();
        if ($row === false) {
            return false;
        }
        $model = clone $this->model;
        // move
        $callback($model);
        foreach ($row as $key => $value) {
            if (str_starts_with($key, "$unique_id.")) {
                $key = substr($key, strlen($unique_id) + 1);
            }
            $callbackInternalData($model, $key, $value);
        }
        $this->resultSet[] = $model;
        return $model;
    }

    /**
     * @throws Exception
     */
    public function fetchAll(): array
    {
        while ($this->fetch() !== false) {
            // pass
        }
        return $this->resultSet;
    }

    /**
     * @throws Exception
     */
    public function first() : false|Model
    {
        if ($this->resultSet === null) {
            $this->fetch();
        }
        return $this->resultSet[0]??false;
    }

    /**
     * @throws Exception
     */
    public function last() : false|Model
    {
        $current = false;
        foreach ($this->fetchAll() as $item) {
            $current = $item;
        }
        return $current;
    }

    /**
     * @throws Exception
     */
    public function offset(int $offset) : false|Model
    {
        foreach ($this->fetchAll() as $key => $item) {
            if ($key === $offset) {
                return $item;
            }
        }
        return false;
    }

    /**
     * @return Traversable
     * @throws Exception
     */
    public function getIterator() : Traversable
    {
        return new ArrayIterator($this->fetchAll());
    }

    public function __destruct()
    {
        $this->resultSet = null;
        $this->result->free();
    }
}
