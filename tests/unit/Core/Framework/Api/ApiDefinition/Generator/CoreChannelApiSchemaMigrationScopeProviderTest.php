<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CoreChannelApiSchemaMigrationScopeProvider;

/**
 * @internal
 */
#[CoversClass(CoreChannelApiSchemaMigrationScopeProvider::class)]
class CoreChannelApiSchemaMigrationScopeProviderTest extends TestCase
{
    public function testProvidesCoreScopeConfiguration(): void
    {
        $provider = new CoreChannelApiSchemaMigrationScopeProvider('/schema', '/allowlist.json');

        static::assertSame('core', $provider->getScope());
        static::assertSame([
            'Contena\\Administration\\',
            'Contena\\Core\\',
            'Contena\\Frontend\\',
        ], $provider->getDefinitionClassPrefixes());
        static::assertSame(['/schema'], $provider->getSchemaPaths());
        static::assertSame('/allowlist.json', $provider->getAllowlistPath());
        static::assertFalse($provider->includesAllDefinitions());
    }

    public function testUsesCoreDefaults(): void
    {
        $provider = new CoreChannelApiSchemaMigrationScopeProvider();
        $projectDirectory = \dirname(__DIR__, 7);

        static::assertSame('core', $provider->getScope());
        static::assertSame([
            $projectDirectory . '/src/Core/Framework/Api/ApiDefinition/Generator/Schema/ChannelApi',
        ], $provider->getSchemaPaths());
        static::assertSame(
            $projectDirectory . '/src/Core/Framework/Api/ApiDefinition/Generator/ChannelApiPhpGeneratedSchemaAllowlist.json',
            $provider->getAllowlistPath(),
        );
    }
}
