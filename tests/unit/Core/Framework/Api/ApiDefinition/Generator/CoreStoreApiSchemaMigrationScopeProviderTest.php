<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CoreStoreApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CoreStoreApiSchemaMigrationScopeProvider::class)]
class CoreStoreApiSchemaMigrationScopeProviderTest extends TestCase
{
    public function testProvidesCoreScopeConfiguration(): void
    {
        $provider = new CoreStoreApiSchemaMigrationScopeProvider('/schema');

        static::assertSame('core', $provider->getScope());
        static::assertSame([
            'Contena\\Administration\\',
            'Contena\\Core\\',
            'Contena\\Elasticsearch\\',
            'Contena\\Frontend\\',
        ], $provider->getDefinitionClassPrefixes());
        static::assertSame(['/schema'], $provider->getSchemaPaths());
        static::assertFalse($provider->includesAllDefinitions());
    }

    public function testUsesCoreDefaults(): void
    {
        $provider = new CoreStoreApiSchemaMigrationScopeProvider();
        $projectDirectory = \dirname(__DIR__, 7);

        static::assertSame('core', $provider->getScope());
        static::assertSame([
            $projectDirectory . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi',
        ], $provider->getSchemaPaths());
    }
}
