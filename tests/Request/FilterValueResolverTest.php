<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Request;

use MapperBundle\Mapper\MapperInterface;
use ODMBundle\Attribute\OdmFilterMapper;
use ODMBundle\Request\FilterValueResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ExceptionHandlerBundle\Exception\ValidationException;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(FilterValueResolver::class)]
final class FilterValueResolverTest extends TestCase
{
    public function testResolveReturnsEmptyWhenNoAttribute(): void
    {
        $mapper = $this->createMock(MapperInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $resolver = new FilterValueResolver($mapper, $validator);

        $request = new Request();
        $argument = new ArgumentMetadata('dto', TestFilterDto::class, false, false, null);

        self::assertSame([], iterator_to_array($resolver->resolve($request, $argument)));
    }

    public function testResolveMapsMergesFiltersAddsSortAndValidates(): void
    {
        $mapper = $this->createMock(MapperInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new FilterValueResolver($mapper, $validator);

        // Route params + query filter (empty strings must be removed)
        $request = new Request();
        $request->attributes = new ParameterBag(['_route_params' => ['id' => '123', 'empty' => '']]);
        $request->query = new InputBag(['filter' => ['q' => 'abc', 'empty2' => ''], 'sort' => ['title', '-createdAt']]);

        $argument = new ArgumentMetadata(
            'dto',
            TestFilterDto::class,
            false,
            false,
            null,
            attributes: [new OdmFilterMapper()],
        );

        $expectedDataPassedToMapper = ['id' => '123', 'q' => 'abc']; // empty keys dropped

        $mapper->expects(self::once())
            ->method('convert')
            ->with(
                self::callback(static function (array $data) use ($expectedDataPassedToMapper) {
                    // order is not guaranteed; compare as sets
                    ksort($data);
                    ksort($expectedDataPassedToMapper);

                    return $data === $expectedDataPassedToMapper;
                }),
                TestFilterDto::class,
            )
            ->willReturn(new TestFilterDto())
        ;

        $validator->expects(self::once())
            ->method('validate')
            ->with(self::isInstanceOf(TestFilterDto::class))
            ->willReturn(new ConstraintViolationList())
        ;

        $result = iterator_to_array($resolver->resolve($request, $argument));
        self::assertCount(1, $result);
        self::assertInstanceOf(TestFilterDto::class, $result[0]);

        // The resolver should inject "sort" when property exists and query has it:
        /** @var TestFilterDto $dto */
        $dto = $result[0];
        self::assertSame(['title', '-createdAt'], $dto->sort);
    }

    public function testResolveThrowsValidationExceptionOnErrors(): void
    {
        $mapper = $this->createMock(MapperInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $resolver = new FilterValueResolver($mapper, $validator);

        $request = new Request();
        $request->attributes = new ParameterBag(['_route_params' => []]);
        $request->query = new InputBag(['filter' => ['q' => 'x']]);

        $argument = new ArgumentMetadata(
            'dto',
            TestFilterDto::class,
            false,
            false,
            null,
            attributes: [new OdmFilterMapper()],
        );

        $mapper->method('convert')->willReturn(new TestFilterDto());
        // Return something countable with >0, and having getIterator()
        $validator->method('validate')->willReturn(
            new ConstraintViolationList([
                $this->createMock(ConstraintViolationInterface::class),
            ]),
        );

        $this->expectException(ValidationException::class);
        iterator_to_array($resolver->resolve($request, $argument));
    }
}

/** simple DTO used by tests */
final class TestFilterDto
{
    /** @var string[] */
    public array $sort = [];
}
