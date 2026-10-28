<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Route\ApiRouteLoader;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApiRouteLoader::class)]
class ApiRouteLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $loader = new ApiRouteLoader($definitionRegistry);

        static::assertTrue($loader->supports('resource', ApiRouteScope::ID));

        $routes = $loader->load('resource');

        static::assertCount(8, $routes);
        static::assertArrayHasKey('api.product.detail', $routes->all());
        static::assertArrayHasKey('api.product.update', $routes->all());
        static::assertArrayHasKey('api.product.delete', $routes->all());
        static::assertArrayHasKey('api.product.list', $routes->all());
        static::assertArrayHasKey('api.product.search', $routes->all());
        static::assertArrayHasKey('api.product.search-ids', $routes->all());
        static::assertArrayHasKey('api.product.create', $routes->all());

        $this->expectExceptionObject(ApiException::apiRoutesAreAlreadyLoaded());
        $loader->load('resource', ApiRouteScope::ID);
    }
}
