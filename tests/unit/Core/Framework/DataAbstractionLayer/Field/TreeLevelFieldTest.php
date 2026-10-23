<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;

/**
 * @internal
 */
#[CoversClass(TreeLevelField::class)]
class TreeLevelFieldTest extends TestCase
{
    public function testConstructorConfiguresStorageAndSystemScopeWriteProtection(): void
    {
        $field = new TreeLevelField('level', 'level');

        static::assertSame('level', $field->getStorageName());
        static::assertSame('level', $field->getPropertyName());

        $flag = $field->getFlag(WriteProtected::class);
        static::assertInstanceOf(WriteProtected::class, $flag);
        static::assertSame([Context::SYSTEM_SCOPE], $flag->getAllowedScopes());
    }
}
