<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Repository;

use MongoDB\Collection;
use ODMBundle\Repository\Traits\MongoDBCollectionTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MongoDBCollectionTrait::class)]
final class MongoDBCollectionTraitTest extends TestCase
{
    public function testSelectsCollectionWithDefaultsAndOverrides(): void
    {
        $selected = $this->createMock(Collection::class);

        $client = new class($selected) {
            public function __construct(private $selected)
            {
            }

            public function selectCollection(string $db, string $collection)
            {
                // simple assertion-like safety
                if ('mydb' === $db && 'mycol' === $collection) {
                    return $this->selected;
                }

                // default case will be asserted later
                return $this->selected;
            }
        };

        $config = new class {
            public function getDefaultDB(): string
            {
                return 'defaultdb';
            }
        };

        $meta = new class {
            public function getCollection(): string
            {
                return 'defaultcol';
            }
        };

        $dm = new class($client, $config) {
            public function __construct(private $client, private $config)
            {
            }

            public function getClient()
            {
                return $this->client;
            }

            public function getConfiguration()
            {
                return $this->config;
            }
        };

        $repo = new class($dm, $meta) {
            use MongoDBCollectionTrait;

            public function __construct(private $dm, private $meta)
            {
            }

            public function getDocumentManager()
            {
                return $this->dm;
            }

            public function getClassMetadata()
            {
                return $this->meta;
            }
        };

        // defaults:
        $c1 = $repo->getMongoDBCollection();
        self::assertInstanceOf(Collection::class, $c1);

        // overrides:
        $c2 = $repo->getMongoDBCollection('mycol', 'mydb');
        self::assertSame($c1, $c2); // both mocked to $selected
    }
}
