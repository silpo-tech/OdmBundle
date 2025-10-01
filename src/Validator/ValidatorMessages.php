<?php

declare(strict_types=1);

namespace ODMBundle\Validator;

class ValidatorMessages
{
    public const VALIDATION__EXISTS = 'validation.exists';
    public const VALIDATION__NOT_EXISTS = 'validation.not_exists';
    public const VALIDATION__DATE_RANGE__INVALID_FORMAT = 'validation.date_range.invalid_format';
    public const VALIDATION__DATE_RANGE__FROM_GREATER_TO = 'validation.date_range.from_greater_to';
    public const VALIDATION__DATE_RANGE__FROM_EQUALS_TO = 'validation.date_range.from_equals_to';
}
