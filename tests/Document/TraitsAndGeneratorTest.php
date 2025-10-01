<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Document;

use Doctrine\ODM\MongoDB\DocumentManager as DocumentManagerAlias;
use ODMBundle\Document\Generator\RamseyUuidGenerator;
use ODMBundle\Document\Traits\CreatedAtTrait;
use ODMBundle\Document\Traits\IdTrait;
use ODMBundle\Document\Traits\UpdatedAtTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass as stdClassAlias;

#[CoversClass(RamseyUuidGenerator::class)]
#[CoversClass(IdTrait::class)]
#[CoversClass(CreatedAtTrait::class)]
#[CoversClass(UpdatedAtTrait::class)]
final class TraitsAndGeneratorTest extends TestCase
{
    public function testRamseyUuidGeneratorReturnsV4(): void
    {
        $gen = new RamseyUuidGenerator();
        $uuid = $gen->generate($this->createMock(DocumentManagerAlias::class), new stdClassAlias());

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid,
        );
    }

    public function testIdTraitGetterSetter(): void
    {
        $doc = new class {
            use IdTrait;
        };

        $doc->setId('abc');
        self::assertSame('abc', $doc->getId());
    }

    public function testCreatedAtTraitLifecycle(): void
    {
        $doc = new class {
            use CreatedAtTrait;
        };

        self::assertNull($doc->getCreatedAt());
        $doc->prePersistCreatedAt();
        self::assertNotNull($doc->getCreatedAt());

        $dt = new \DateTime();
        $doc->setCreatedAt($dt);
        $doc->prePersistCreatedAt();
        self::assertSame($dt, $doc->getCreatedAt());
    }

    public function testUpdatedAtTraitLifecycle(): void
    {
        $doc = new class {
            use UpdatedAtTrait;
        };

        self::assertNull($doc->getUpdatedAt());
        $doc->prePersistUpdateAt();
        $first = $doc->getUpdatedAt();
        self::assertNotNull($first);

        // PreUpdate always sets "now"
        $before = new \DateTimeImmutable();
        $doc->preUpdateUpdateAt();
        self::assertTrue($doc->getUpdatedAt() >= $first);
        self::assertTrue($doc->getUpdatedAt() >= $before);
    }
}
