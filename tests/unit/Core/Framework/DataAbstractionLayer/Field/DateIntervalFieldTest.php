<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\DateIntervalFieldSerializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(DateIntervalField::class)]
class DateIntervalFieldTest extends TestCase
{
    private DateIntervalField $field;

    protected function setUp(): void
    {
        $this->field = new DateIntervalField('name', 'name');
    }

    public function testGetStorageName(): void
    {
        static::assertSame('name', $this->field->getStorageName());
    }

    public function testGetSerializerWillReturnFieldSerializerInterfaceInstance(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry
            ->method('getSerializer')
            ->willReturn(
                new DateIntervalFieldSerializer(
                    static::createStub(ValidatorInterface::class),
                    $registry
                )
            );
        $registry->method('getResolver');
        $registry->method('getAccessorBuilder');
        $this->field->compile($registry);

        static::assertInstanceOf(DateIntervalFieldSerializer::class, $this->field->getSerializer());
    }
}
