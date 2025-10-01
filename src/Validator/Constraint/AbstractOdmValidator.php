<?php

declare(strict_types=1);

namespace ODMBundle\Validator\Constraint;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

abstract class AbstractOdmValidator extends ConstraintValidator
{
    protected string $allowedConstraintClass = '';

    private DocumentManager $dm;

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
    }

    abstract protected function performValidation($value, OdmConstraint $constraint, $result): void;

    public function validate($value, Constraint $constraint): void
    {
        if (!$this->allowedConstraintClass) {
            throw new RuntimeException('Missing required constraint class parameter');
        }

        /* @var OdmConstraint $constraint */
        $this->validateConstraint($constraint);

        if (null === $value) {
            return;
        }

        $criteria = $this->getCriteria($value, $constraint);

        if ($constraint->ignoreNull && !count($criteria)) {
            return;
        }

        $result = $this->getResult($criteria, $constraint);

        $this->performValidation($value, $constraint, $result);
    }

    protected function validateConstraint(Constraint $constraint): void
    {
        /** @var OdmConstraint $constraint */
        if (!$constraint instanceof OdmConstraint || !$constraint instanceof $this->allowedConstraintClass) {
            throw new UnexpectedTypeException($constraint, $this->allowedConstraintClass);
        }

        if (!isset($constraint->documentClass) || !isset($constraint->fields)) {
            throw new InvalidArgumentException('Missing required constraint parameters');
        }

        if (0 === count($constraint->fields)) {
            throw new ConstraintDefinitionException('At least one field has to be specified.');
        }
    }

    protected function getRepository(OdmConstraint $constraint): ObjectRepository
    {
        try {
            return $this->dm->getRepository($constraint->documentClass);
        } catch (\Throwable $e) {
            // Normalize to a consistent validator exception type
            throw new ConstraintDefinitionException(sprintf('Repository for document %s not found', $constraint->documentClass), 0, $e);
        }
    }

    protected function getCriteria($value, OdmConstraint $constraint): array
    {
        $fields = $constraint->fields;
        $criteria = [];

        foreach ($fields as $valueField => $documentField) {
            if ($constraint->ignoreNull && $value->$valueField === null) {
                continue;
            }

            $criteria[$documentField] = $value->$valueField;
        }

        return $criteria;
    }

    protected function getResult(array $criteria, OdmConstraint $constraint)
    {
        return $this->getRepository($constraint)->{$constraint->repositoryMethod}($criteria);
    }

    protected function addViolation(OdmConstraint $constraint): void
    {
        $this->context->buildViolation($constraint->message)->atPath($constraint->errorPath)->addViolation();
    }
}
