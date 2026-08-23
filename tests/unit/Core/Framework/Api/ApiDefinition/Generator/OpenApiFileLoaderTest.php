<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApiFileLoader;

/**
 * @internal
 */
#[CoversClass(OpenApiFileLoader::class)]
class OpenApiFileLoaderTest extends TestCase
{
    public function testMergingOfFiles(): void
    {
        $paths = [__DIR__ . '/_fixtures/Api/ApiDefinition/Generator/Schema/AdminApi'];
        $fsLoader = new OpenApiFileLoader($paths);

        $spec = $fsLoader->loadOpenapiSpecification();

        static::assertArrayHasKey('paths', $spec);
        static::assertArrayHasKey('components', $spec);
        static::assertArrayHasKey('/api/_info/config', $spec['paths']);
        static::assertArrayHasKey('schemas', $spec['components']);
        static::assertSame(['infoConfigResponse'], array_keys($spec['components']['schemas']));
    }

    public function testEmptyFileLoader(): void
    {
        $fsLoader = new OpenApiFileLoader([]);

        $spec = $fsLoader->loadOpenapiSpecification();

        static::assertSame(
            [
                'paths' => [],
                'components' => [],
                'tags' => [],
            ],
            $spec
        );
    }

    public function testMergingFilesUsesDeterministicNameOrder(): void
    {
        $paths = [__DIR__ . '/_fixtures/Api/ApiDefinition/Generator/Schema/DeterministicOrder'];
        $fsLoader = new OpenApiFileLoader($paths);

        $spec = $fsLoader->loadOpenapiSpecification();

        static::assertSame('Sorted last', $spec['paths']['/deterministic']['get']['description']);
    }
}
