<?php

declare(strict_types=1);

namespace ODMBundle\Paginator;

use Doctrine\ODM\MongoDB\Aggregation\Builder as AggregationBuilder;
use Doctrine\ODM\MongoDB\Query\Builder as QueryBuilder;
use PaginatorBundle\Paginator\OffsetPaginator;
use RestBundle\DTO\ListDTO;

class BuilderAwarePaginator
{
    public function paginate(object $qb, OffsetPaginator $paginator): ListDTO
    {
        switch ($qb) {
            case $qb instanceof AggregationBuilder:
                return $this->aggregationPaginate($qb, $paginator);
            case $qb instanceof QueryBuilder:
                return $this->queryPaginate($qb, $paginator);
            default:
                throw new \RuntimeException('Valid MongoDB builder is required');
        }
    }

    protected function aggregationPaginate(AggregationBuilder $qb, OffsetPaginator $paginator): ListDTO
    {
        $cqb = clone $qb;

        $count = $cqb
            ->hydrate('')
            ->count('total')
            ->getAggregation()
            ->getIterator()
            ->current()['total'] ?? 0
        ;

        $items = $qb
            ->skip($paginator->getOffset())
            ->limit($paginator->getLimit())
            ->getAggregation()
            ->getIterator()
            ->toArray()
        ;

        return $this->createListDto($count, $items);
    }

    protected function queryPaginate(QueryBuilder $qb, OffsetPaginator $paginator): ListDTO
    {
        $cqb = clone $qb;

        $count = $cqb
            ->count()
            ->getQuery()
            ->execute()
        ;

        $items = $qb
            ->limit($paginator->getLimit())
            ->skip($paginator->getOffset())
            ->getQuery()
            ->execute()
            ->toArray()
        ;

        return $this->createListDto($count, $items);
    }

    private function createListDto(int $count, $items): ListDTO
    {
        return new ListDTO($count, $items);
    }
}
