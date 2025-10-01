<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Validator;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Persistence\ObjectRepository;
use ODMBundle\Validator\Constraint\AbstractOdmValidator;
use ODMBundle\Validator\Constraint\OdmConstraint;
use ODMBundle\Validator\Constraint\OdmExists;
use ODMBundle\Validator\Constraint\OdmExistsValidator;
use ODMBundle\Validator\Constraint\OdmNotExists;
use ODMBundle\Validator\Constraint\OdmNotExistsValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException as RuntimeExceptionAlias;
use stdClass as stdClassAlias;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException as InvalidArgumentExceptionAlias;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Exception\RuntimeException as SfRuntimeException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

#[CoversClass(AbstractOdmValidator::class)]
#[CoversClass(OdmExistsValidator::class)]
#[CoversClass(OdmNotExistsValidator::class)]
final class AbstractOdmValidatorTest extends TestCase
{
    public function testMissingAllowedConstraintClassThrows(): void
    {
        $dm = $this->createMock(DocumentManager::class);

        $validator = new class($dm) extends AbstractOdmValidator {
            public function __construct(private readonly DocumentManager $dm)
            {
                parent::__construct($dm);
            }

            protected function performValidation($value, OdmConstraint $constraint, $result): void
            {
            }
            // Intentionally no $allowedConstraintClass to cover the guard.
        };

        $this->expectException(SfRuntimeException::class);
        $validator->validate(new stdClassAlias(), new OdmExists(['documentClass' => stdClassAlias::class, 'fields' => ['a' => 'aDoc']]));
    }

    public function testInvalidConstraintTypeThrows(): void
    {
        $dm = $this->createMock(DocumentManager::class);
        $validator = new OdmExistsValidator($dm);

        // Build a valid OdmNotExists constraint so the validator reaches the type check.
        $wrongConstraint = new OdmNotExists(['documentClass' => stdClassAlias::class, 'fields' => ['a' => 'aDoc']]);

        $this->expectException(UnexpectedTypeException::class);
        $validator->validate(new stdClassAlias(), $wrongConstraint);
    }

    public function testMissingRequiredParametersThrows(): void
    {
        // The constraint constructor enforces required options and throws MissingOptionsException.
        $this->expectException(MissingOptionsException::class);
        new OdmExists(); // no options provided -> throws immediately
    }

    public function testEmptyFieldsThrows(): void
    {
        $dm = $this->createMock(DocumentManager::class);
        $validator = new OdmExistsValidator($dm);

        $constraint = new OdmExists([
            'documentClass' => stdClassAlias::class,
            'fields' => [], // empty array triggers validator-level check
        ]);

        $this->expectException(ConstraintDefinitionException::class);
        $validator->validate(new stdClassAlias(), $constraint);
    }

    public function testIgnoreNullSkipsValidationWhenAllNulls(): void
    {
        $repo = $this->createMock(ObjectRepository::class);
        $dm = $this->createMock(DocumentManager::class);
        $dm->method('getRepository')->willReturn($repo);

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->expects(self::never())->method('buildViolation');

        $v = new OdmExistsValidator($dm);
        $v->initialize($ctx);

        $c = new OdmExists([
            'documentClass' => stdClassAlias::class,
            'fields' => ['x' => 'xDoc'],
            'ignoreNull' => true,
        ]);

        $value = (object) ['x' => null];

        $v->validate($value, $c);

        $this->addToAssertionCount(1);
    }

    public function testCriteriaIncludesNullsWhenIgnoreNullFalse(): void
    {
        $repo = $this->createMock(ObjectRepository::class);
        $repo->expects(self::once())
            ->method('findOneBy')
            ->with(self::equalTo(['xDoc' => null]))
            ->willReturn(null)
        ;

        $dm = $this->createMock(DocumentManager::class);
        $dm->method('getRepository')->willReturn($repo);

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->method('buildViolation')->willReturn($this->createMock(ConstraintViolationBuilderInterface::class)); // not used

        $v = new OdmExistsValidator($dm);
        $v->initialize($ctx);

        $c = new OdmExists([
            'documentClass' => stdClassAlias::class,
            'fields' => ['x' => 'xDoc'],
            'ignoreNull' => false,
        ]);

        $value = new stdClassAlias();
        $value->x = null;

        $v->validate($value, $c);
        $this->addToAssertionCount(1);
    }

    public function testOdmExistsAddsViolationWhenNotFound(): void
    {
        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $dm = $this->createMock(DocumentManager::class);
        $dm->method('getRepository')->willReturn($repo);

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('atPath')->with('odm')->willReturnSelf();
        $builder->expects(self::once())->method('addViolation');

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->method('buildViolation')->with('validation.not_exists')->willReturn($builder);

        $v = new OdmExistsValidator($dm);
        $v->initialize($ctx);

        $c = new OdmExists([
            'documentClass' => stdClassAlias::class,
            'fields' => ['a' => 'aDoc'],
        ]);

        $value = new stdClassAlias();
        $value->a = 10;

        $v->validate($value, $c);
    }

    public function testOdmNotExistsAddsViolationWhenDifferentId(): void
    {
        $found = new class {
            public function getId(): string
            {
                return 'db-1';
            }
        };

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($found);

        $dm = $this->createMock(DocumentManager::class);
        $dm->method('getRepository')->willReturn($repo);

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects(self::once())->method('atPath')->with('odm')->willReturnSelf();
        $builder->expects(self::once())->method('addViolation');

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->method('buildViolation')->with('validation.exists')->willReturn($builder);

        $v = new OdmNotExistsValidator($dm);
        $v->initialize($ctx);

        $c = new OdmNotExists([
            'documentClass' => stdClassAlias::class,
            'fields' => ['code' => 'codeDoc'],
        ]);

        $value = new class {
            public string $id = 'dto-2';
            public string $code = 'C';
        };

        $v->validate($value, $c);
    }

    public function testOdmNotExistsNoViolationWhenSameId(): void
    {
        $found = new class {
            public function getId(): string
            {
                return 'same';
            }
        };

        $repo = $this->createMock(ObjectRepository::class);
        $repo->method('findOneBy')->willReturn($found);

        $dm = $this->createMock(DocumentManager::class);
        $dm->method('getRepository')->willReturn($repo);

        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->expects(self::never())->method('buildViolation');

        $v = new OdmNotExistsValidator($dm);
        $v->initialize($ctx);

        $c = new OdmNotExists([
            'documentClass' => stdClassAlias::class,
            'fields' => ['code' => 'codeDoc'],
        ]);

        $value = new class {
            public string $id = 'same';
            public string $code = 'C';
        };

        $v->validate($value, $c);
        $this->addToAssertionCount(1);
    }

    public function testValidateConstraintThrowsInvalidArgumentWhenRequiredParamsMissing(): void
    {
        $dm = $this->createMock(DocumentManager::class);

        // Validator wired to accept our dummy constraint class
        $validator = new class($dm) extends AbstractOdmValidator {
            protected string $allowedConstraintClass = DummyConstraintForValidatorTest::class;

            public function __construct(DocumentManager $dm)
            {
                parent::__construct($dm);
            }

            protected function performValidation($value, OdmConstraint $constraint, $result): void
            {
            }
            // Do not override getRepository(): we fail earlier on missing params.
        };

        // Dummy constraint with no required options set
        $constraint = new DummyConstraintForValidatorTest(); // documentClass/fields are not set

        $this->expectException(InvalidArgumentExceptionAlias::class);
        $validator->validate(new stdClassAlias(), $constraint);
    }

    public function testValidateConstraintThrowsConstraintDefinitionWhenRepositoryMissing(): void
    {
        $dm = $this->createMock(DocumentManager::class);

        // Simulate ODM being unable to resolve the repository; getRepository() will catch and return null.
        $dm->method('getRepository')->willThrowException(new RuntimeExceptionAlias('Unmapped class'));

        $validator = new class($dm) extends AbstractOdmValidator {
            protected string $allowedConstraintClass = DummyConstraintForValidatorTest::class;

            public function __construct(DocumentManager $dm)
            {
                parent::__construct($dm);
            }

            protected function performValidation($value, OdmConstraint $constraint, $result): void
            {
            }
        };

        $constraint = new DummyConstraintForValidatorTest();
        $constraint->documentClass = stdClassAlias::class;           // satisfy earlier guards
        $constraint->fields = ['x' => 'xDoc'];

        $this->expectException(ConstraintDefinitionException::class);
        $validator->validate((object) ['x' => 123], $constraint);
    }

    public function testValidateReturnsEarlyOnNullValue(): void
    {
        // DocumentManager must not be touched when value is null
        $dm = $this->createMock(DocumentManager::class);
        $dm->expects(self::never())->method('getRepository');

        // No violations should be built
        $ctx = $this->createMock(ExecutionContextInterface::class);
        $ctx->expects(self::never())->method('buildViolation');

        // Validator configured to accept our dummy constraint
        $validator = new class($dm) extends AbstractOdmValidator {
            protected string $allowedConstraintClass = DummyConstraintForValidatorTest::class;

            public function __construct(DocumentManager $dm)
            {
                parent::__construct($dm);
            }

            protected function performValidation($value, OdmConstraint $constraint, $result): void
            {
                // Should never be called for null value
                throw new RuntimeExceptionAlias('performValidation must not be called');
            }
        };
        $validator->initialize($ctx);

        // Provide a valid constraint instance so type check passes
        $constraint = new DummyConstraintForValidatorTest();
        $constraint->documentClass = stdClassAlias::class;
        $constraint->fields = ['x' => 'xDoc'];

        // Exercise the early-return path
        $validator->validate(null, $constraint);

        // If we got here, early return worked without touching DM or context
        $this->addToAssertionCount(1);
    }
}

final class DummyConstraintForValidatorTest extends OdmConstraint
{
    // Override to avoid Symfony's constructor-level MissingOptionsException,
    // so validator's own "missing params" guard is exercised.
    public function getRequiredOptions(): array
    {
        return [];
    }

    public string $documentClass;

    public array $fields;
}
