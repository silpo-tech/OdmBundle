<?php

declare(strict_types=1);

namespace ODMBundle\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class OdmConstraint extends Constraint
{
    public string $errorPath = 'odm';
    public string $message;

    public string $documentClass;
    public array $fields;
    public string $repositoryMethod = 'findOneBy';
    public bool $ignoreNull = true;

    public function getRequiredOptions(): array
    {
        return ['documentClass', 'fields'];
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
