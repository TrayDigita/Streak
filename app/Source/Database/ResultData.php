<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Database;

use Countable;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use IteratorAggregate;
use Traversable;
use TrayDigita\Streak\Source\Database\Abstracts\Model;

class ResultData implements IteratorAggregate, Countable
{
    private readonly Model $model;

    /**
     * @var ?Result
     */
    private ?Result $result = null;

    /**
     * @param Model $model
     */
    public function __construct(
        Model $model
    ) {
        $this->model = $model;
    }

    private function setInternalData(?array $value) : Model
    {
        $model = get_class($this->model);
        $model = new $model($this->model->instance);
        $model->setInternalData($value);
        $model->queryBuilder->resetQueryParts();
        return $model;
    }

    /**
     * @return ?Result
     * @throws Exception
     */
    public function result(): ?Result
    {
        $this->result ??= $this->model->queryBuilder->executeQuery();
        return $this->result;
    }

    /**
     * @param array|false|null $value
     *
     * @return ?Model
     */
    private function createModel(array|false|null $value = null): ?Model
    {
        return $value ? $this->setInternalData($value) : null;
    }

    /**
     * @return object<Model>|null
     * @throws Exception
     */
    public function fetchFirst() : ?Model
    {
        return $this->createModel(
            (clone $this->model->queryBuilder)
                ->setMaxResults(1)
                ->setFirstResult(0)
                ->fetchAssociative()
        );
    }

    /**
     * @return object<Model>|null
     * @throws Exception
     */
    public function fetchTake(int $offset) : ?Model
    {
        return $this->createModel(
            (clone $this->model->queryBuilder)
                ->setFirstResult($offset)
                ->setMaxResults(1)
                ->fetchAssociative()
        );
    }

    /**
     * @return object<Model>|null
     * @throws Exception
     */
    public function fetchLast(): ?Model
    {
        $count = $this->count();
        if ($count < 1) {
            return null;
        }
        return $this->createModel(
            (clone $this->model->queryBuilder)
                ->setFirstResult($count-1)
                ->setMaxResults(1)
                ->fetchAssociative()
        );
    }

    /**
     * @return object<Model>|null
     * @throws Exception
     */
    public function fetch(): ?Model
    {
        return $this->createModel($this->result()->fetchAssociative());
    }

    /**
     * @return array<object<Model>>
     * @throws Exception
     */
    public function fetchAll(): array
    {
        return array_map([$this, 'createModel'], $this->result()->fetchAllAssociative());
    }

    /**
     * @throws Exception
     */
    public function fetchArray(): array|false
    {
        return $this->result()->fetchAssociative();
    }

    /**
     * @throws Exception
     */
    public function fetchAllArray(): array|false
    {
        return $this->result()->fetchAllAssociative();
    }

    /**
     * @throws Exception
     */
    public function fetchAssociative(): array|false
    {
        return $this->result()->fetchAssociative();
    }

    /**
     * @throws Exception
     */
    public function fetchAllAssociative(): array|false
    {
        return $this->result()->fetchAllAssociative();
    }

    /**
     * @return Traversable
     * @throws Exception
     */
    public function getIterator() : Traversable
    {
        while ($row = $this->fetch()) {
            yield $row;
        }
    }

    /**
     * @throws Exception
     */
    public function count() : int
    {
        return $this?->result()->rowCount()?:0;
    }

    public function restart(): static
    {
        $this->result = null;
        return $this;
    }

    public function free()
    {
        $this->result?->free();
        $this->result = null;
    }

    public function __destruct()
    {
        $this->free();
    }
}
