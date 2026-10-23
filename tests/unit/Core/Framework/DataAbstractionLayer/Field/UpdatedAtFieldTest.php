<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;

/**
 * @internal
 */
#[CoversClass(UpdatedAtField::class)]
class UpdatedAtFieldTest extends TestCase
{
    public function testConstructorConfiguresTheStorageName(): void
    {
        $field = new UpdatedAtField();

        static::assertSame('updated_at', $field->getStorageName());
        static::assertSame('updatedAt', $field->getPropertyName());
    }
}
