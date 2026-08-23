<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Event\EventData\FormDataObjectType;
use Contena\Core\Framework\Event\EventData\ObjectType;

/**
 * @internal
 */
#[CoversClass(FormDataObjectType::class)]
class FormDataObjectTypeTest extends TestCase
{
    public function testToArrayKeepsObjectTypeShapeAndAddsMarker(): void
    {
        static::assertSame(
            [
                'type' => ObjectType::TYPE,
                'data' => null,
                FormDataObjectType::MARKER => true,
            ],
            new FormDataObjectType()->toArray()
        );
    }
}
