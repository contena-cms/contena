<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\CustomEntity\Schema;

use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\System\CustomEntity\CustomEntityException;
use Contena\Core\System\CustomEntity\Schema\DynamicFieldFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * @internal
 */
#[CoversClass(DynamicFieldFactory::class)]
class DynamicFieldFactoryTest extends TestCase
{
    public function testCreateThrowsAnExceptionWhenTheServiceIsNotFound(): void
    {
        $this->expectExceptionObject(new ServiceNotFoundException('Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry'));

        $factory = new DynamicFieldFactory();

        $factory->create(static::createStub(ContainerInterface::class), 'test', [
            ['name' => 'test', 'type' => '', 'reference' => '', 'onDelete' => ''],
        ]);
    }

    public function testGetDeletedFlagThrowsAnExceptionWhenTheFieldIsUnmatched(): void
    {
        $this->expectExceptionObject(CustomEntityException::unsupportedOnDeletePropertyOnField('INVALID', 'test'));

        $factory = new DynamicFieldFactory();

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->willReturn(static::createStub(DefinitionInstanceRegistry::class));

        $factory->create($container, 'test', [
            ['name' => 'test', 'type' => 'many-to-one', 'reference' => 'unit', 'onDelete' => 'INVALID'],
        ]);
    }
}
