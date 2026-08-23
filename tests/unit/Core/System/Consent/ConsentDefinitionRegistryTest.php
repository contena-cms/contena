<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Consent\ConsentDefinitionRegistry;
use Contena\Core\System\Consent\ConsentException;

/**
 * @internal
 */
#[CoversClass(ConsentDefinitionRegistry::class)]
class ConsentDefinitionRegistryTest extends TestCase
{
    public function testAllReturnsDefinitionsKeyedByName(): void
    {
        $backendData = new TestDefinition('backend_data', 'system');
        $adminTracking = new TestDefinition('admin_tracking', 'admin_user');
        $registry = new ConsentDefinitionRegistry([$backendData, $adminTracking]);

        static::assertSame([
            'backend_data' => $backendData,
            'admin_tracking' => $adminTracking,
        ], $registry->all());
    }

    public function testGetReturnsDefinitionByName(): void
    {
        $backendData = new TestDefinition('backend_data', 'system');
        $registry = new ConsentDefinitionRegistry([$backendData]);

        static::assertSame($backendData, $registry->get('backend_data'));
    }

    public function testGetThrowsIfDefinitionDoesNotExist(): void
    {
        $registry = new ConsentDefinitionRegistry([]);

        $this->expectExceptionObject(ConsentException::notFound('backend_data'));

        $registry->get('backend_data');
    }
}
