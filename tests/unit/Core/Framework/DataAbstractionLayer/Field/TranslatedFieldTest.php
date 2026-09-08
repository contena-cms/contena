<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TranslatedField::class)]
class TranslatedFieldTest extends TestCase
{
    public function testInstantiate(): void
    {
        $field = new TranslatedField('name');

        static::assertFalse($field->useForSorting());

        $field = new TranslatedField(
            'name',
            true,
        );

        static::assertSame('name', $field->getPropertyName());
        static::assertTrue($field->useForSorting());
    }
}
