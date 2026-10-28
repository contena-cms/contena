<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\Generator\AllStoreApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AllStoreApiSchemaMigrationScopeProvider::class)]
class AllStoreApiSchemaMigrationScopeProviderTest extends TestCase
{
    public function testProvidesAllScopeConfiguration(): void
    {
        $provider = new AllStoreApiSchemaMigrationScopeProvider(
            new BundleSchemaPathCollection([new ContenaBundleWithName()]),
            '/schema',
        );

        static::assertSame('all', $provider->getScope());
        static::assertSame([], $provider->getDefinitionClassPrefixes());
        static::assertSame([
            '/schema',
            __DIR__ . '/_fixtures/CustomBundleWithApiSchema/Resources/Schema/StoreApi',
        ], $provider->getSchemaPaths());
        static::assertTrue($provider->includesAllDefinitions());
    }
}
