<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Route;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Route\ApiRouteLoader;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ApiRouteLoader::class)]
class ApiRouteLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $definitionRegistry = new StaticDefinitionInstanceRegistry(
            [new DateDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $loader = new ApiRouteLoader($definitionRegistry);

        static::assertTrue($loader->supports('resource', ApiRouteScope::ID));

        $routes = $loader->load('resource');

        static::assertCount(8, $routes);
        static::assertArrayHasKey('api._date_field_test.detail', $routes->all());
        static::assertArrayHasKey('api._date_field_test.update', $routes->all());
        static::assertArrayHasKey('api._date_field_test.delete', $routes->all());
        static::assertArrayHasKey('api._date_field_test.list', $routes->all());
        static::assertArrayHasKey('api._date_field_test.search', $routes->all());
        static::assertArrayHasKey('api._date_field_test.search-ids', $routes->all());
        static::assertArrayHasKey('api._date_field_test.create', $routes->all());

        $this->expectExceptionObject(ApiException::apiRoutesAreAlreadyLoaded());
        $loader->load('resource', ApiRouteScope::ID);
    }
}
