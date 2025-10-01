<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Attribute;

use ODMBundle\Attribute\OdmFilterMapper;
use ODMBundle\Request\FilterValueResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass as stdClassAlias;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Constraints\GroupSequence;

#[CoversClass(OdmFilterMapper::class)]
final class OdmFilterMapperTest extends TestCase
{
    public function testOptionsAndFlags(): void
    {
        $gs = new GroupSequence(['Default', 'ODM']);

        $attr = new OdmFilterMapper(
            resolver: FilterValueResolver::class,
            validationGroups: $gs,
            propertyValidationGroups: ['name' => ['Default']],
            options: ['foo' => 'bar'],
            isOptional: true,
            isGroupSequenceEnabled: true,
        );

        // emulate Symfony setting metadata
        $meta = new ArgumentMetadata('dto', stdClassAlias::class, false, false, null);
        $attr->metadata = $meta;

        self::assertSame($gs, $attr->getValidationGroups());
        self::assertSame(['name' => ['Default']], $attr->getPropertyValidationGroups());
        self::assertSame(['foo' => 'bar'], $attr->getOptions());
        self::assertTrue($attr->isOptional());
        self::assertTrue($attr->isGroupSequenceEnabled());
        self::assertSame($meta, $attr->metadata);
    }
}
