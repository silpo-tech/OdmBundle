<?php

declare(strict_types=1);

namespace ODMBundle\Validator\Constraint;

use ODMBundle\Validator\ValidatorMessages;

#[\Attribute]
class OdmNotExists extends OdmConstraint
{
    public string $message = ValidatorMessages::VALIDATION__EXISTS;
}
