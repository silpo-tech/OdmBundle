<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Paginator {
    use ArrayIterator;
    use Doctrine\ODM\MongoDB\Aggregation\Aggregation;
    use Doctrine\ODM\MongoDB\Aggregation\Builder as AggregationBuilder;
    use Doctrine\ODM\MongoDB\Aggregation\Stage\Count;
    use Doctrine\ODM\MongoDB\Aggregation\Stage\Limit as LimitAlias;
    use Doctrine\ODM\MongoDB\Aggregation\Stage\Skip as SkipAlias;
    use Doctrine\ODM\MongoDB\Iterator\Iterator as IteratorAlias;
    use Doctrine\ODM\MongoDB\Query\Builder as QueryBuilder;
    use Doctrine\ODM\MongoDB\Query\Query;
    use ODMBundle\Paginator\BuilderAwarePaginator;
    use PaginatorBundle\Paginator\OffsetPaginator;
    use PHPUnit\Framework\Attributes\CoversClass;
    use PHPUnit\Framework\TestCase;
    use stdClass as stdClassAlias;

    #[CoversClass(BuilderAwarePaginator::class)]
    final class BuilderAwarePaginatorTest extends TestCase
    {
        public function testAggregationPaginate(): void
        {
            $p = new BuilderAwarePaginator();

            // Mock Aggregation\Builder
            $qb = $this->getMockBuilder(AggregationBuilder::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['hydrate', 'count', 'skip'])
                ->getMock()
            ;

            // count() must return Stage\Count (not self)
            $countStage = $this->getMockBuilder(Count::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getAggregation'])
                ->getMock()
            ;

            // Aggregation for the "count" branch
            $aggForCount = $this->getMockBuilder(Aggregation::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getIterator'])
                ->getMock()
            ;

            // Concrete iterator for the "count" branch
            $countIterator = new class([['total' => 5]]) extends ArrayIterator implements IteratorAlias {
                public function __construct(array $data)
                {
                    parent::__construct($data);
                }

                public function toArray(): array
                {
                    return iterator_to_array($this);
                }
            };
            $aggForCount->method('getIterator')->willReturn($countIterator);

            // Link count() -> getAggregation() -> count iterator
            $qb->expects(self::atLeastOnce())->method('hydrate')->with('')->willReturnSelf();
            $qb->expects(self::atLeastOnce())->method('count')->with('total')->willReturn($countStage);
            $countStage->method('getAggregation')->willReturn($aggForCount);

            // Aggregation for the "items" branch
            $aggForItems = $this->getMockBuilder(Aggregation::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getIterator'])
                ->getMock()
            ;

            // Concrete iterator for items list
            $itemsIterator = new class([['a' => 1], ['b' => 2]]) extends ArrayIterator implements IteratorAlias {
                public function __construct(array $data)
                {
                    parent::__construct($data);
                }

                public function toArray(): array
                {
                    return iterator_to_array($this);
                }
            };
            $aggForItems->method('getIterator')->willReturn($itemsIterator);

            // Stage chain: skip() -> Stage\Skip; Stage\Skip::limit() -> Stage\Limit; Stage\Limit::getAggregation() -> Aggregation
            $skipStage = $this->getMockBuilder(SkipAlias::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['limit'])
                ->getMock()
            ;

            $limitStage = $this->getMockBuilder(LimitAlias::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getAggregation'])
                ->getMock()
            ;

            $qb->expects(self::once())->method('skip')->with(20)->willReturn($skipStage);
            $skipStage->expects(self::once())->method('limit')->with(10)->willReturn($limitStage);
            $limitStage->method('getAggregation')->willReturn($aggForItems);

            // Paginator providing offset/limit
            $paginator = new class(20, 10) extends OffsetPaginator {
                public function __construct(private readonly int $offset, private readonly int $limit)
                {
                }

                public function getOffset(): int
                {
                    return $this->offset;
                }

                public function getLimit(): int
                {
                    return $this->limit;
                }
            };

            $list = $p->paginate($qb, $paginator);

            self::assertSame(5, $list->total);
            self::assertSame([['a' => 1], ['b' => 2]], $list->items);
        }

        public function testQueryPaginate(): void
        {
            $p = new BuilderAwarePaginator();

            // Query mock for the "count" branch: execute() -> int
            $countQuery = $this->getMockBuilder(Query::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['execute'])
                ->getMock()
            ;
            $countQuery->method('execute')->willReturn(7);

            // Query mock for the "items" branch: execute() -> cursor-like object with toArray()
            $itemsQuery = $this->getMockBuilder(Query::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['execute'])
                ->getMock()
            ;
            $itemsQuery->method('execute')->willReturn(new class {
                public function toArray(): array
                {
                    return [['x' => 1]];
                }
            });

            // Lightweight test double of Query\Builder.
            // IMPORTANT: return types must match the parent methods exactly.
            $qb = new class($countQuery, $itemsQuery) extends QueryBuilder {
                private string $mode = 'items';

                public function __construct(
                    private readonly Query $countQuery,
                    private readonly Query $itemsQuery,
                ) {
                    // Intentionally not calling parent constructor
                }

                public function __clone(): void
                {
                    // Shallow clone is fine; no internals to copy
                }

                public function count(): QueryBuilder
                {
                    $this->mode = 'count';

                    return $this;
                }

                public function skip(int $skip): QueryBuilder
                {
                    $this->mode = 'items';

                    return $this;
                }

                public function limit(int $limit): QueryBuilder
                {
                    $this->mode = 'items';

                    return $this;
                }

                public function getQuery(array $options = []): Query
                {
                    return 'count' === $this->mode ? $this->countQuery : $this->itemsQuery;
                }
            };

            $paginator = new class extends OffsetPaginator {
                public function getOffset(): int
                {
                    return 0;
                }

                public function getLimit(): int
                {
                    return 2;
                }
            };

            $list = $p->paginate($qb, $paginator);

            self::assertSame(7, $list->total);
            self::assertSame([['x' => 1]], $list->items);
        }

        public function testInvalidBuilderThrows(): void
        {
            $p = new BuilderAwarePaginator();
            $this->expectException(\RuntimeException::class);
            $p->paginate(new stdClassAlias(), new class extends OffsetPaginator {
            });
        }
    }
} // end namespace ODMBundle\Tests\Paginator

namespace RestBundle\DTO {
    final class ListDTO
    {
        public function __construct(public int $total, public array $items)
        {
        }
    }
}

namespace PaginatorBundle\Paginator {
    abstract class OffsetPaginator
    {
        public function getOffset(): int
        {
            return 0;
        }

        public function getLimit(): int
        {
            return 50;
        }
    }
}
