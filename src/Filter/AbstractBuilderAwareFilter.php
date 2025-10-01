<?php

declare(strict_types=1);

namespace ODMBundle\Filter;

use Doctrine\ODM\MongoDB\Aggregation\Builder as AggregationBuilder;
use Doctrine\ODM\MongoDB\Aggregation\Stage\MatchStage;
use Doctrine\ODM\MongoDB\Query\Builder as QueryBuilder;

abstract class AbstractBuilderAwareFilter
{
    public const DATE_FIELD_FROM = 'from';
    public const DATE_FIELD_TO = 'to';

    protected static string $aggregationMethodPrefix = 'aggregation';
    protected static string $queryMethodPrefix = 'query';

    public function filter(object $qb, object $filterDto)
    {
        switch ($qb) {
            case $qb instanceof AggregationBuilder:
                $this->aggregationFilter($qb, $filterDto);

                break;
            case $qb instanceof QueryBuilder:
                $this->queryFilter($qb, $filterDto);

                break;
            default:
                throw new \RuntimeException('Valid MongoDB builder is required');
        }
    }

    protected function aggregationFilter(AggregationBuilder $qb, object $filterDto)
    {
        $match = $qb->match();

        foreach ((array) $filterDto as $key => $value) {
            $methodName = self::$aggregationMethodPrefix.ucfirst($key);
            if (isset($value) && method_exists($this, $methodName)) {
                $this->{$methodName}($match, $value);
            }
        }
    }

    protected function aggregationFilterDate(MatchStage $match, array $value, string $field, bool $inclusive = true)
    {
        $field = $match->field($field);

        if ($from = $value[self::DATE_FIELD_FROM] ?? null) {
            $inclusive ? $field->gte($from) : $field->gt($from);
        }

        if ($to = $value[self::DATE_FIELD_TO] ?? null) {
            $inclusive ? $field->lte($to) : $field->lt($to);
        }
    }

    protected function aggregationSort(MatchStage $match, array $value): void
    {
        foreach ($value as $field) {
            $match->sort(...$this->resolveSortArguments($field));
        }
    }

    public function queryFilter(QueryBuilder $qb, object $filterDto)
    {
        foreach ((array) $filterDto as $key => $value) {
            $methodName = self::$queryMethodPrefix.ucfirst($key);
            if (isset($value) && method_exists($this, $methodName)) {
                $this->{$methodName}($qb, $value);
            }
        }
    }

    protected function queryFilterDate(QueryBuilder $qb, array $value, string $field, bool $inclusive = true): void
    {
        $field = $qb->field($field);

        if ($from = $value[self::DATE_FIELD_FROM] ?? null) {
            $inclusive ? $field->gte($from) : $field->gt($from);
        }

        if ($to = $value[self::DATE_FIELD_TO] ?? null) {
            $inclusive ? $field->lte($to) : $field->lt($to);
        }
    }

    protected function querySort(QueryBuilder $qb, array $value): void
    {
        foreach ($value as $field) {
            $qb->sort(...$this->resolveSortArguments($field));
        }
    }

    private function resolveSortArguments(string $value): array
    {
        return '-' === $value[0] ? [substr($value, 1), 'DESC'] : [$value, 'ASC'];
    }
}
