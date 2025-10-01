<?php

declare(strict_types=1);

namespace ODMBundle\Validator\Constraint;

class OdmNotExistsValidator extends AbstractOdmValidator
{
    protected string $allowedConstraintClass = OdmNotExists::class;

    protected function performValidation($value, OdmConstraint $constraint, $result): void
    {
        if ($result && $result->getId() !== $value->id) {
            $this->addViolation($constraint);
        }
    }
}
