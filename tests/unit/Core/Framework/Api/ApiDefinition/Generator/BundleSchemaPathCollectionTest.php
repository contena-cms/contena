<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures\CustomBundleWithApiSchema\ContenaBundleWithName;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * @internal
 */
#[CoversClass(BundleSchemaPathCollection::class)]
class BundleSchemaPathCollectionTest extends TestCase
{
    private Bundle $bundleWithSchemas;

    private Bundle $bundleWithoutSchemas;

    private Bundle $customBundleSchemas;

    protected function setUp(): void
    {
        $this->bundleWithSchemas = static::createStub(Bundle::class);
        $this->bundleWithSchemas->method('getPath')->willReturn(__DIR__ . '/_fixtures/BundleWithApiSchema');
        $this->bundleWithoutSchemas = static::createStub(Bundle::class);
        $this->bundleWithoutSchemas->method('getPath')->willReturn(__DIR__ . '/_fixtures/BundleWithoutApiSchema');
        $this->customBundleSchemas = new ContenaBundleWithName();
    }

    public function testGetPathsForChannelApi(): void
    {
        $factory = new BundleSchemaPathCollection([$this->bundleWithSchemas, $this->bundleWithoutSchemas]);

        $paths = $factory->getSchemaPaths(DefinitionService::CHANNEL_API, null);
        static::assertContains(__DIR__ . '/_fixtures/BundleWithApiSchema/Resources/Schema/ChannelApi', $paths);
        static::assertNotContains(__DIR__ . '/_fixtures/BundleWithoutApiSchema/Resources/Schema/ChannelApi', $paths);
    }

    public function testGetPathsForAdminApi(): void
    {
        $factory = new BundleSchemaPathCollection([$this->bundleWithSchemas, $this->bundleWithoutSchemas]);

        $paths = $factory->getSchemaPaths(DefinitionService::API, null);
        static::assertContains(__DIR__ . '/_fixtures/BundleWithApiSchema/Resources/Schema/AdminApi', $paths);
        static::assertNotContains(__DIR__ . '/_fixtures/BundleWithoutApiSchema/Resources/Schema/AdminApi', $paths);
    }

    public function testGetPathsForSingleBundleAdminApi(): void
    {
        $factory = new BundleSchemaPathCollection([$this->bundleWithSchemas, $this->bundleWithoutSchemas, $this->customBundleSchemas]);

        $paths = $factory->getSchemaPaths(DefinitionService::API, $this->customBundleSchemas->getName());
        static::assertContains(__DIR__ . '/_fixtures/CustomBundleWithApiSchema/Resources/Schema/AdminApi', $paths);
        static::assertNotContains(__DIR__ . '/_fixtures/BundleWithApiSchema/Resources/Schema/AdminApi', $paths);
        static::assertNotContains(__DIR__ . '/_fixtures/BundleWithoutApiSchema/Resources/Schema/AdminApi', $paths);
    }

    public function testGetPathsForSingleBundleChannelApi(): void
    {
        $factory = new BundleSchemaPathCollection([$this->bundleWithSchemas, $this->bundleWithoutSchemas, $this->customBundleSchemas]);

        $paths = $factory->getSchemaPaths(DefinitionService::CHANNEL_API, $this->customBundleSchemas->getName());
        static::assertContains(__DIR__ . '/_fixtures/CustomBundleWithApiSchema/Resources/Schema/ChannelApi', $paths);
        static::assertNotContains(__DIR__ . '/_fixtures/BundleWithApiSchema/Resources/Schema/ChannelApi', $paths);
        static::assertNotContains(__DIR__ . '/_fixtures/BundleWithoutApiSchema/Resources/Schema/ChannelApi', $paths);
    }
}
