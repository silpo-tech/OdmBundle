<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Validator;

use ODMBundle\Validator\Constraint\OdmConstraint;
use ODMBundle\Validator\Constraint\OdmExists;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass as stdClassAlias;
use Symfony\Component\Validator\Constraint;

#[CoversClass(OdmConstraint::class)]
final class OdmConstraintTest extends TestCase
{
    public function testTargetsAndRequiredOptions(): void
    {
        // Provide required options so the constraint can be constructed.
        $c = new OdmExists([
            'documentClass' => stdClassAlias::class,
            'fields' => ['x' => 'xDoc'],
        ]);

        // Assert required options list exposed by the abstract base.
        self::assertSame(['documentClass', 'fields'], $c->getRequiredOptions());

        // Assert the constraint target type (class-level).
        self::assertSame(Constraint::CLASS_CONSTRAINT, $c->getTargets());
    }
}
