<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\Generator\AllChannelApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ContenaBundleWithName;

/**
 * @internal
 */
#[CoversClass(AllChannelApiSchemaMigrationScopeProvider::class)]
class AllChannelApiSchemaMigrationScopeProviderTest extends TestCase
{
    public function testProvidesAllScopeConfiguration(): void
    {
        $provider = new AllChannelApiSchemaMigrationScopeProvider(
            new BundleSchemaPathCollection([new ContenaBundleWithName()]),
            '/schema',
            '/allowlist.json',
        );

        static::assertSame('all', $provider->getScope());
        static::assertSame([], $provider->getDefinitionClassPrefixes());
        static::assertSame([
            '/schema',
            __DIR__ . '/_fixtures/CustomBundleWithApiSchema/Resources/Schema/ChannelApi',
        ], $provider->getSchemaPaths());
        static::assertSame('/allowlist.json', $provider->getAllowlistPath());
        static::assertTrue($provider->includesAllDefinitions());
    }
}
