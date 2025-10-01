<?php

declare(strict_types=1);

namespace ODMBundle\Validator\Constraint;

use ODMBundle\Validator\ValidatorMessages;
use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidDateRange extends Constraint
{
    public string $messageInvalidFormat = ValidatorMessages::VALIDATION__DATE_RANGE__INVALID_FORMAT;
    public string $messageFromGreaterTo = ValidatorMessages::VALIDATION__DATE_RANGE__FROM_GREATER_TO;
    public string $messageFromEqualsTo = ValidatorMessages::VALIDATION__DATE_RANGE__FROM_EQUALS_TO;

    public string $format = 'Y-m-d';
    public bool $allowEqual = true;
}
