<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Validator;

use ODMBundle\Filter\AbstractBuilderAwareFilter;
use ODMBundle\Validator\Constraint\ValidDateRange;
use ODMBundle\Validator\Constraint\ValidDateRangeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint as ConstraintAlias;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

#[CoversClass(ValidDateRangeValidator::class)]
final class ValidDateRangeValidatorTest extends TestCase
{
    public function testUnexpectedConstraintType(): void
    {
        $v = new ValidDateRangeValidator();
        $this->expectException(UnexpectedTypeException::class);
        $v->validate([], $this->createMock(ConstraintAlias::class));
    }

    public function testNullValueIsOk(): void
    {
        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->expects(self::never())->method('buildViolation');

        $v = new ValidDateRangeValidator();
        $v->initialize($ctx);

        $c = new ValidDateRange();

        $v->validate(null, $c);
        $this->addToAssertionCount(1);
    }

    public function testNonArrayThrows(): void
    {
        $v = new ValidDateRangeValidator();
        $this->expectException(UnexpectedValueException::class);
        $v->validate('2025-01-01', new ValidDateRange());
    }

    public function testInvalidFormatTriggersViolation(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('addViolation');

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->method('buildViolation')
            ->with('validation.date_range.invalid_format')
            ->willReturn($builder)
        ;

        $v = new ValidDateRangeValidator();
        $v->initialize($ctx);

        $c = new ValidDateRange();
        $bad = [
            AbstractBuilderAwareFilter::DATE_FIELD_FROM => '01-01-2025', // wrong format
            AbstractBuilderAwareFilter::DATE_FIELD_TO => '2025-01-02',
        ];
        $v->validate($bad, $c);
    }

    public function testFromGreaterThanToTriggersViolation(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('addViolation');

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->method('buildViolation')
            ->with('validation.date_range.from_greater_to')
            ->willReturn($builder)
        ;

        $v = new ValidDateRangeValidator();
        $v->initialize($ctx);

        $c = new ValidDateRange();
        $v->validate([
            'from' => '2025-01-03',
            'to' => '2025-01-02',
        ], $c);
    }

    public function testEqualDatesViolationWhenNotAllowed(): void
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('addViolation');

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->method('buildViolation')
            ->with('validation.date_range.from_equals_to')
            ->willReturn($builder)
        ;

        $v = new ValidDateRangeValidator();
        $v->initialize($ctx);

        $c = new ValidDateRange();
        $c->allowEqual = false;

        $v->validate(['from' => '2025-01-02', 'to' => '2025-01-02'], $c);
    }

    public function testValidRangeNoViolation(): void
    {
        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->expects(self::never())->method('buildViolation');

        $v = new ValidDateRangeValidator();
        $v->initialize($ctx);

        $c = new ValidDateRange();
        $v->validate(['from' => '2025-01-01', 'to' => '2025-01-02'], $c);

        $this->addToAssertionCount(1);
    }

    public function testNullItemInsideArrayTriggersInvalidFormatViolation(): void
    {
        // Expect "invalid_format" when one of the date values is null (array value provided)
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('addViolation');

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->method('buildViolation')
            ->with('validation.date_range.invalid_format')
            ->willReturn($builder)
        ;

        $v = new ValidDateRangeValidator();
        $v->initialize($ctx);

        $c = new ValidDateRange();

        $v->validate([
            AbstractBuilderAwareFilter::DATE_FIELD_FROM => null,               // <- hits isValidValue null branch
            AbstractBuilderAwareFilter::DATE_FIELD_TO => '2025-01-02',
        ], $c);
    }

    public function testNonStringItemInsideArrayTriggersInvalidFormatViolation(): void
    {
        // Expect "invalid_format" when one of the date values is not a string
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('addViolation');

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->method('buildViolation')
            ->with('validation.date_range.invalid_format')
            ->willReturn($builder)
        ;

        $v = new ValidDateRangeValidator();
        $v->initialize($ctx);

        $c = new ValidDateRange();

        $v->validate([
            AbstractBuilderAwareFilter::DATE_FIELD_FROM => 123,                // <- hits isValidValue non-string branch
            AbstractBuilderAwareFilter::DATE_FIELD_TO => '2025-01-02',
        ], $c);
    }
}
