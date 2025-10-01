<?php

declare(strict_types=1);

namespace ODMBundle\Validator\Constraint;

use ODMBundle\Filter\AbstractBuilderAwareFilter;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValidDateRangeValidator extends ConstraintValidator
{
    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidDateRange) {
            throw new UnexpectedTypeException($constraint, ValidDateRange::class);
        }

        if (null === $value) {
            return;
        }

        if (!is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        $keyFrom = AbstractBuilderAwareFilter::DATE_FIELD_FROM;
        $keyTo = AbstractBuilderAwareFilter::DATE_FIELD_TO;

        foreach ($value as $key => $val) {
            if (!in_array($key, [$keyFrom, $keyTo]) || !$this->isValidValue($val, $constraint->format)) {
                $this->context->buildViolation($constraint->messageInvalidFormat)->addViolation();

                return;
            }
        }

        if (!empty($value[$keyFrom]) && !empty($value[$keyTo])) {
            $from = $this->createFromFormat($value[$keyFrom], $constraint->format);
            $to = $this->createFromFormat($value[$keyTo], $constraint->format);

            if ($from > $to) {
                $this->context->buildViolation($constraint->messageFromGreaterTo)->addViolation();

                return;
            }

            if ($from == $to && !$constraint->allowEqual) {
                $this->context->buildViolation($constraint->messageFromEqualsTo)->addViolation();

                return;
            }
        }
    }

    private function isValidValue($value, string $format): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = $this->createFromFormat($value, $format);

        return $date && $date->format($format) === $value;
    }

    private function createFromFormat(string $value, string $format): \DateTimeInterface|bool
    {
        return \DateTime::createFromFormat($format, $value);
    }
}
