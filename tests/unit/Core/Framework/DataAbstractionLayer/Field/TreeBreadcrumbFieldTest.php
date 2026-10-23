<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreeBreadcrumbField;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(TreeBreadcrumbField::class)]
class TreeBreadcrumbFieldTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $field = new TreeBreadcrumbField();

        static::assertSame('breadcrumb', $field->getStorageName());
        static::assertSame('breadcrumb', $field->getPropertyName());
        static::assertSame('name', $field->getNameField());

        $flag = $field->getFlag(WriteProtected::class);
        static::assertInstanceOf(WriteProtected::class, $flag);
        static::assertSame([Context::SYSTEM_SCOPE], $flag->getAllowedScopes());
    }

    public function testConstructorPassesExplicitValuesToTheParent(): void
    {
        $field = new TreeBreadcrumbField('auto_breadcrumb', 'autoBreadcrumb', 'title');

        static::assertSame('auto_breadcrumb', $field->getStorageName());
        static::assertSame('autoBreadcrumb', $field->getPropertyName());
        static::assertSame('title', $field->getNameField());
    }
}
