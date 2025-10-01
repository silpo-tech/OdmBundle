<?php

declare(strict_types=1);

namespace ODMBundle\Request;

use MapperBundle\Mapper\MapperInterface;
use ODMBundle\Attribute\OdmFilterMapper;
use SilpoTech\ExceptionHandlerBundle\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FilterValueResolver implements ValueResolverInterface
{
    private MapperInterface $mapper;
    private ValidatorInterface $validator;

    public function __construct(MapperInterface $mapper, ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $this->mapper = $mapper;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(OdmFilterMapper::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;
        if (!$attribute) {
            return [];
        }

        $argumentType = $argument->getType();

        $data = array_merge(
            $request->attributes->get('_route_params', []),
            $request->query->all('filter') ?? [],
        );
        $data = array_filter($data, static fn ($v) => is_array($v) ? $v : strlen($v));

        $dto = $this->mapper->convert($data, $argumentType);

        $this->addSort($request, $dto);

        $errors = $this->validator->validate(
            $dto,
            null,
            $attribute->getValidationGroups(),
        );

        if (count($errors)) {
            throw new ValidationException((array) (method_exists($errors, 'getIterator') ? $errors->getIterator() : $errors));
        }

        return [$dto];
    }

    private function addSort(Request $request, object $dto): void
    {
        if (!property_exists($dto, 'sort') || !$request->query->has('sort')) {
            return;
        }

        $dto->sort = $request->query->all('sort');
    }
}
