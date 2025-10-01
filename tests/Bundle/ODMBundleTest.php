<?php

declare(strict_types=1);

namespace ODMBundle\Tests\Bundle;

use ODMBundle\ODMBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ODMBundle::class)]
final class ODMBundleTest extends TestCase
{
    public function testBundleExtendsSymfonyBundle(): void
    {
        $bundle = new ODMBundle();

        self::assertSame('ODMBundle', $bundle->getName());
    }
}
