<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User;

use Contena\Core\System\User\UserDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(UserDefinition::class)]
class UserDefinitionTest extends TestCase
{
    public function testUsesTheChineseDefaultTimeZone(): void
    {
        static::assertSame(
            ['timeZone' => 'Asia/Shanghai'],
            new UserDefinition()->getDefaults()
        );
    }
}
