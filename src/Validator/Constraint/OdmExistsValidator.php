<?php

declare(strict_types=1);

namespace ODMBundle\Validator\Constraint;

class OdmExistsValidator extends AbstractOdmValidator
{
    protected string $allowedConstraintClass = OdmExists::class;

    protected function performValidation($value, OdmConstraint $constraint, $result): void
    {
        if (!$result) {
            $this->addViolation($constraint);
        }
    }
}
