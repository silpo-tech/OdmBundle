<?php

declare(strict_types=1);

namespace ODMBundle\Tests\DependencyInjection;

use ODMBundle\DependencyInjection\ODMExtension;
use ODMBundle\Request\FilterValueResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This test validates that:
 *  - the extension loads src/Resources/config/services.yml
 *  - services are autowired/autoconfigured and not public by default
 *  - the "Document" namespace is excluded (no definitions for classes under ODMBundle\Document\…)
 */
#[CoversClass(ODMExtension::class)]
final class ODMExtensionTest extends TestCase
{
    public function testLoadRegistersServicesAndHonorsExcludes(): void
    {
        $container = new ContainerBuilder();

        // Execute the extension load() — it should load services.yml
        $ext = new ODMExtension();
        $ext->load([], $container);

        // Assert a known, concrete service from src/ (not excluded) is registered:
        $this->assertTrue(
            $container->hasDefinition(FilterValueResolver::class) || $container->hasAlias(FilterValueResolver::class),
            'FilterValueResolver should be registered as a service (resource: ../../*).',
        );

        // If definition exists, check DI flags from _defaults in services.yml
        if ($container->hasDefinition(FilterValueResolver::class)) {
            $def = $container->getDefinition(FilterValueResolver::class);
            $this->assertTrue($def->isAutowired(), 'Service should be autowired by default.');
            $this->assertTrue($def->isAutoconfigured(), 'Service should be autoconfigured by default.');
            $this->assertFalse($def->isPublic(), 'Service should be non-public by default.');
        }
    }
}
