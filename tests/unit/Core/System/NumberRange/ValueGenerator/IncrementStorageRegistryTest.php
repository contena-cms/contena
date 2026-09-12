<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\NumberRange\ValueGenerator;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\NumberRangeException;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementState;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[CoversClass(IncrementStorageRegistry::class)]
class IncrementStorageRegistryTest extends TestCase
{
    private IncrementStorageRegistry $registry;

    private AbstractIncrementStorage&MockObject $mainStorage;

    private AbstractIncrementStorage&MockObject $secondaryStorage;

    private Context $context;

    protected function setUp(): void
    {
        $this->mainStorage = $this->createMock(AbstractIncrementStorage::class);
        $this->secondaryStorage = $this->createMock(AbstractIncrementStorage::class);
        $this->context = Context::createDefaultContext();

        $this->registry = new IncrementStorageRegistry(
            new ServiceLocator([
                'main' => fn () => $this->mainStorage,
                'secondary' => fn () => $this->secondaryStorage,
            ]),
            'main'
        );
    }

    public function testGetDefaultStorage(): void
    {
        $storage = $this->registry->getStorage();
        static::assertSame($this->mainStorage, $storage);
    }

    public function testGetNamedStorage(): void
    {
        $storage = $this->registry->getStorage('secondary');
        static::assertSame($this->secondaryStorage, $storage);
    }

    public function testGetUnknownStorageThrows(): void
    {
        static::expectExceptionObject(NumberRangeException::incrementStorageNotFound('foo', ['main', 'secondary']));
        $this->registry->getStorage('foo');
    }

    public function testMigrate(): void
    {
        $first = new IncrementState($this->context->getDataScopeId(), Uuid::randomHex(), 0);
        $second = new IncrementState($this->context->getDataScopeId(), Uuid::randomHex(), 15);
        $sourceValues = [
            $first->numberRangeId => $first,
            $second->numberRangeId => $second,
        ];
        $this->mainStorage->expects($this->once())
            ->method('list')
            ->with($this->context)
            ->willReturn($sourceValues);

        $targetValues = [];
        $this->secondaryStorage->method('set')
            ->willReturnCallback(static function (IncrementState $state, Context $context) use (&$targetValues): void {
                static::assertSame($context->getDataScopeId(), $state->dataScopeId);
                $targetValues[$state->numberRangeId] = $state;
            });

        $this->registry->migrate('main', 'secondary', $this->context);
        static::assertSame($sourceValues, $targetValues);
    }

    public function testMigrateWithUnknownFromStorageThrows(): void
    {
        static::expectExceptionObject(NumberRangeException::incrementStorageNotFound('foo', ['main', 'secondary']));
        $this->registry->migrate('foo', 'secondary', $this->context);
    }

    public function testMigrateWithUnknownToStorageThrows(): void
    {
        static::expectExceptionObject(NumberRangeException::incrementStorageNotFound('foo', ['main', 'secondary']));
        $this->registry->migrate('main', 'foo', $this->context);
    }
}
