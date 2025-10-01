<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Filter;

use Doctrine\ODM\MongoDB\Aggregation\Builder as AggregationBuilder;
use Doctrine\ODM\MongoDB\Aggregation\Stage\MatchStage;
use Doctrine\ODM\MongoDB\Query\Builder as QueryBuilder;
use ODMBundle\Filter\AbstractBuilderAwareFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass as stdClassAlias;

#[CoversClass(AbstractBuilderAwareFilter::class)]
final class AbstractBuilderAwareFilterTest extends TestCase
{
    public function testFilterDispatchesToAggregation(): void
    {
        $filter = new class extends AbstractBuilderAwareFilter {
            public array $called = [];

            protected function aggregationFilter(AggregationBuilder $qb, $filterDto): void
            {
                $this->called[] = 'agg';
            }

            public function queryFilter(QueryBuilder $qb, $filterDto): void
            {
                $this->called[] = 'qry';
            }

            public function exposeAggregationSort(MatchStage $stage, array $v): void
            {
                $this->aggregationSort($stage, $v);
            }

            public function exposeQuerySort(QueryBuilder $qb, array $v): void
            {
                $this->querySort($qb, $v);
            }
        };

        $qb = $this->createMock(AggregationBuilder::class);
        $filter->filter($qb, new stdClassAlias());

        self::assertSame(['agg'], $filter->called);
    }

    public function testFilterDispatchesToQuery(): void
    {
        $filter = new class extends AbstractBuilderAwareFilter {
            public array $called = [];

            protected function aggregationFilter(AggregationBuilder $qb, $filterDto): void
            {
                $this->called[] = 'agg';
            }

            public function queryFilter(QueryBuilder $qb, $filterDto): void
            {
                $this->called[] = 'qry';
            }

            public function exposeAggregationSort(MatchStage $stage, array $v): void
            {
                $this->aggregationSort($stage, $v);
            }

            public function exposeQuerySort(QueryBuilder $qb, array $v): void
            {
                $this->querySort($qb, $v);
            }
        };

        $qb = $this->createMock(QueryBuilder::class);
        $filter->filter($qb, new stdClassAlias());

        self::assertSame(['qry'], $filter->called);
    }

    public function testFilterThrowsOnInvalidBuilder(): void
    {
        $filter = new class extends AbstractBuilderAwareFilter {
            protected function aggregationFilter(AggregationBuilder $qb, $filterDto): void
            {
            }

            public function queryFilter(QueryBuilder $qb, $filterDto): void
            {
            }
        };

        $this->expectException(\RuntimeException::class);
        $filter->filter(new stdClassAlias(), new stdClassAlias());
    }

    public function testAggregationSortUsesCorrectDirections(): void
    {
        $filter = new class extends AbstractBuilderAwareFilter {
            protected function aggregationFilter(AggregationBuilder $qb, $filterDto): void
            {
            }

            public function queryFilter(QueryBuilder $qb, $filterDto): void
            {
            }

            public function exposeAggregationSort(MatchStage $stage, array $v): void
            {
                $this->aggregationSort($stage, $v);
            }
        };

        $stage = $this->getMockBuilder(MatchStage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sort'])
            ->getMock()
        ;

        $calls = [];
        $stage->expects(self::exactly(2))
            ->method('sort')
            ->willReturnCallback(static function (string $field, string $direction) use (&$calls, $stage) {
                $calls[] = [$field, $direction];

                return $stage;
            })
        ;

        $filter->exposeAggregationSort($stage, ['title', '-createdAt']);

        self::assertSame(
            [
                ['title', 'ASC'],
                ['createdAt', 'DESC'],
            ],
            $calls,
        );
    }

    public function testQuerySortUsesCorrectDirections(): void
    {
        $filter = new class extends AbstractBuilderAwareFilter {
            protected function aggregationFilter(AggregationBuilder $qb, $filterDto): void
            {
            }

            public function queryFilter(QueryBuilder $qb, $filterDto): void
            {
            }

            public function exposeQuerySort(QueryBuilder $qb, array $v): void
            {
                $this->querySort($qb, $v);
            }
        };

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sort'])
            ->getMock()
        ;

        $calls = [];
        $qb->expects(self::exactly(2))
            ->method('sort')
            ->willReturnCallback(static function (string $field, string $direction) use (&$calls, $qb) {
                $calls[] = [$field, $direction];

                return $qb;
            })
        ;

        $filter->exposeQuerySort($qb, ['title', '-createdAt']);

        self::assertSame(
            [
                ['title', 'ASC'],
                ['createdAt', 'DESC'],
            ],
            $calls,
        );
    }

    public function testAggregationFilterInvokesAggregationDateAndAppliesInclusiveBounds(): void
    {
        // Concrete subclass that maps DTO property "date" to the protected date method.
        $filter = new class extends AbstractBuilderAwareFilter {
            // exposes protected date helper through the dynamic method name expected by aggregationFilter()
            public function aggregationDate(MatchStage $match, array $value): void
            {
                $this->aggregationFilterDate($match, $value, 'createdAt');
            }

            public function queryFilter(QueryBuilder $qb, $filterDto): void
            {
            }
        };

        // Mock Aggregation\Builder::match() to return a MatchStage that supports field/gte/lte.
        $match = $this->getMockBuilder(MatchStage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['field', 'gte', 'lte'])
            ->getMock()
        ;

        // Expect field('createdAt')->gte($from)->lte($to)
        $from = new \DateTimeImmutable('2025-01-01');
        $to = new \DateTimeImmutable('2025-01-02');

        $match->expects(self::once())->method('field')->with('createdAt')->willReturn($match);
        $match->expects(self::once())->method('gte')->with($from)->willReturn($match);
        $match->expects(self::once())->method('lte')->with($to)->willReturn($match);

        $ab = $this->getMockBuilder(AggregationBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['match'])
            ->getMock()
        ;
        $ab->expects(self::once())->method('match')->willReturn($match);

        // DTO with property "date" so aggregationFilter() resolves to aggregationDate()
        $dto = (object) [
            'date' => [
                AbstractBuilderAwareFilter::DATE_FIELD_FROM => $from,
                AbstractBuilderAwareFilter::DATE_FIELD_TO => $to,
            ],
        ];

        // This covers aggregationFilter() + aggregationFilterDate() inclusive path
        $filter->filter($ab, $dto);
    }

    public function testAggregationFilterDateExclusiveUsesGtLt(): void
    {
        // Subclass exposes an exclusive variant via a different dynamic method.
        $filter = new class extends AbstractBuilderAwareFilter {
            public function aggregationDateExclusive(MatchStage $match, array $value): void
            {
                $this->aggregationFilterDate($match, $value, 'createdAt', false);
            }

            public function queryFilter(QueryBuilder $qb, $filterDto): void
            {
            }
        };

        $match = $this->getMockBuilder(MatchStage::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['field', 'gt', 'lt'])
            ->getMock()
        ;

        $from = new \DateTimeImmutable('2025-01-01');
        $to = new \DateTimeImmutable('2025-01-02');

        $match->expects(self::once())->method('field')->with('createdAt')->willReturn($match);
        $match->expects(self::once())->method('gt')->with($from)->willReturn($match);
        $match->expects(self::once())->method('lt')->with($to)->willReturn($match);

        $ab = $this->getMockBuilder(AggregationBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['match'])
            ->getMock()
        ;
        $ab->expects(self::once())->method('match')->willReturn($match);

        $dto = (object) [
            'dateExclusive' => [
                AbstractBuilderAwareFilter::DATE_FIELD_FROM => $from,
                AbstractBuilderAwareFilter::DATE_FIELD_TO => $to,
            ],
        ];

        // This covers aggregationFilter() dispatch + aggregationFilterDate() exclusive path
        $filter->filter($ab, $dto);
    }

    public function testQueryFilterInvokesQueryDateAndAppliesInclusiveBounds(): void
    {
        // Subclass maps DTO property "date" to queryFilterDate()
        $filter = new class extends AbstractBuilderAwareFilter {
            protected function aggregationFilter(AggregationBuilder $qb, $filterDto)
            {
            }

            public function queryDate(QueryBuilder $qb, array $value): void
            {
                $this->queryFilterDate($qb, $value, 'createdAt');
            }
        };

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['field', 'gte', 'lte'])
            ->getMock()
        ;

        $from = new \DateTimeImmutable('2025-01-01');
        $to = new \DateTimeImmutable('2025-01-02');

        $qb->expects(self::once())->method('field')->with('createdAt')->willReturn($qb);
        $qb->expects(self::once())->method('gte')->with($from)->willReturn($qb);
        $qb->expects(self::once())->method('lte')->with($to)->willReturn($qb);

        $dto = (object) [
            'date' => [
                AbstractBuilderAwareFilter::DATE_FIELD_FROM => $from,
                AbstractBuilderAwareFilter::DATE_FIELD_TO => $to,
            ],
        ];

        // This covers queryFilter() + queryFilterDate() inclusive path
        $filter->queryFilter($qb, $dto);
    }

    public function testQueryFilterDateExclusiveUsesGtLt(): void
    {
        $filter = new class extends AbstractBuilderAwareFilter {
            protected function aggregationFilter(AggregationBuilder $qb, $filterDto)
            {
            }

            public function queryDateExclusive(QueryBuilder $qb, array $value): void
            {
                $this->queryFilterDate($qb, $value, 'createdAt', false);
            }
        };

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['field', 'gt', 'lt'])
            ->getMock()
        ;

        $from = new \DateTimeImmutable('2025-01-01');
        $to = new \DateTimeImmutable('2025-01-02');

        $qb->expects(self::once())->method('field')->with('createdAt')->willReturn($qb);
        $qb->expects(self::once())->method('gt')->with($from)->willReturn($qb);
        $qb->expects(self::once())->method('lt')->with($to)->willReturn($qb);

        $dto = (object) [
            'dateExclusive' => [
                AbstractBuilderAwareFilter::DATE_FIELD_FROM => $from,
                AbstractBuilderAwareFilter::DATE_FIELD_TO => $to,
            ],
        ];

        // This covers queryFilter() dispatch + queryFilterDate() exclusive path
        $filter->queryFilter($qb, $dto);
    }
}
