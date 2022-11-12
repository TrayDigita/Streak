<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Database;

use TrayDigita\Streak\Source\Database\Abstracts\Model;
use TrayDigita\Streak\Source\Helper\Generator\RandomString;

/**
 * @mixin ResultData
 */
class ResultQuery
{
    public readonly Model $model;

    /**
     * @var ?ResultData
     */
    private ?ResultData $lastResult = null;

    /**
     * @var bool
     */
    private bool $hasChange = false;

    /**
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = clone $model;
        $this->model->queryBuilder->resetQueryParts();
        $this->model->queryBuilder->select(
            array_keys($this->model->getModelColumns())?:'*'
        )->from($this->model->getTableName());
    }

    /**
     * @param string $fn
     * @param array $expression
     * @param array $params
     *
     * @return $this
     */
    private function appendStatement(string $fn, array $expression, array $params = []): static
    {
        $this->hasChange = true;
        $qb = $this
            ->model
            ->queryBuilder;
        $qb->{$fn}(...$expression);
        foreach ($params as $name => $value) {
            $qb->setParameter($name, $value);
        }
        return $this;
    }

    /**
     * @param string $method
     * @param string $name
     * @param $value
     *
     * @return array
     */
    private function buildExpression(string $method, string $name, $value): array
    {
        $exp = $this->model->queryBuilder->expr();
        if ($value === null) {
            $equal = ['eq', 'like', 'in'];
            return [in_array($method, $equal) ? $exp->isNull($name) : $exp->isNotNull($name)];
        }
        if (is_array($value)) {
            $value = array_map(
                fn ($e) => $this->model->convertForDatabaseValue($name, $e),
                $value
            );
            $method = $method === 'eq' ? 'in' : 'notIn';
        } else {
            $value = $this->model->convertForDatabaseValue($name, $value);
        }
        $key = RandomString::createUniqueHash();
        return [
            $exp->$method($name, ":$key"),
            [$key => $value]
        ];
    }

    public function where(string $expression, array $params = []) : static
    {
        return $this->appendStatement('where', [$expression], $params);
    }

    public function orWhere(string $expression, array $params = []) : static
    {
        return $this->appendStatement('orWhere', [$expression], $params);
    }

    public function andWhere(string $expression, array $params = []) : static
    {
        return $this->appendStatement('andWhere', [$expression], $params);
    }

    public function is(string $name, $value) : static
    {
        return $this->andWhere(...$this->buildExpression('eq', $name, $value));
    }

    public function orIs(string $name, $value) : static
    {
        return $this->orWhere(...$this->buildExpression('eq', $name, $value));
    }

    public function not(string $name, $value) : static
    {
        return $this->andWhere(...$this->buildExpression('neq', $name, $value));
    }
    public function orNot(string $name, $value) : static
    {
        return $this->orWhere(...$this->buildExpression('neq', $name, $value));
    }

    public function in(string $name, array $value) : static
    {
        return $this->andWhere(...$this->buildExpression('in', $name, $value));
    }

    public function notIn(string $name, array $value) : static
    {
        return $this->andWhere(...$this->buildExpression('notIn', $name, $value));
    }

    public function orIn(string $name, array $value) : static
    {
        return $this->orWhere(...$this->buildExpression('in', $name, $value));
    }

    public function orNotIn(string $name, array $value) : static
    {
        return $this->orWhere(...$this->buildExpression('notIn', $name, $value));
    }

    public function greaterThan($name, $value) : static
    {
        return $this->andWhere(...$this->buildExpression('gt', $name, $value));
    }

    public function orGreaterThan($name, $value) : static
    {
        return $this->orWhere(...$this->buildExpression('gt', $name, $value));
    }

    public function lessThan($name, $value) : static
    {
        return $this->andWhere(...$this->buildExpression('lt', $name, $value));
    }
    public function orLessThan($name, $value) : static
    {
        return $this->orWhere(...$this->buildExpression('lt', $name, $value));
    }

    public function orGreaterOrEqual($name, $value) : static
    {
        return $this->orWhere(...$this->buildExpression('gte', $name, $value));
    }

    public function orLessOrEqual($name, $value) : static
    {
        return $this->orWhere(...$this->buildExpression('lte', $name, $value));
    }

    /**
     * @param string $name
     * @param string $like
     *
     * @return $this
     */
    public function like(string $name, string $like) : static
    {
        return $this->andWhere(...$this->buildExpression('like', $name, $like));
    }

    /**
     * @param string $name
     * @param string $like
     *
     * @return $this
     */
    public function notLike(string $name, string $like) : static
    {
        return $this->andWhere(...$this->buildExpression('notLike', $name, $like));
    }

    /**
     * @param string $name
     * @param string $like
     *
     * @return $this
     */
    public function orLike(string $name, string $like) : static
    {
        return $this->orWhere(...$this->buildExpression('like', $name, $like));
    }

    /**
     * @param string $name
     * @param string $like
     *
     * @return $this
     */
    public function orNotLike(string $name, string $like) : static
    {
        return $this->orWhere(...$this->buildExpression('notLike', $name, $like));
    }

    public function having(string|int|float ...$args): static
    {
        return $this->appendStatement('having', $args);
    }

    public function andHaving(string|int|float ...$args): static
    {
        return $this->appendStatement('andHaving', $args);
    }

    public function orHaving(string|int|float ...$args): static
    {
        return $this->appendStatement('orHaving', $args);
    }

    public function order(?string $sort = null, ?string $order = null): static
    {
        if ($sort === null) {
            $this->model->queryBuilder->resetQueryParts('orderBy');
            return $this;
        }
        return $this->appendStatement('orderBy', func_get_args());
    }

    public function offset(int $offset = 0): static
    {
        return $this->appendStatement('setFirstResult', [$offset]);
    }

    public function limit(int $results = null): static
    {
        return $this->appendStatement('setMaxResults', [$results]);
    }

    /**
     * @param callable $callable
     *
     * @return $this
     */
    public function group(callable $callable) : static
    {
        $callable($this->model->queryBuilder, $this->model, $this);
        return $this;
    }

    /**
     * @return ResultData
     */
    public function getResultData(): ResultData
    {
        if (!$this->lastResult || $this->hasChange) {
            $this->lastResult = new ResultData($this->model);
        }
        return $this->lastResult;
    }

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->getResultData(), $name], $arguments);
    }
}
