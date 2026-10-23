<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;

/**
 * @internal
 */
#[CoversClass(AdminApiSource::class)]
class AdminApiSourceTest extends TestCase
{
    public function testPermissions(): void
    {
        $apiSource = new AdminApiSource(null, null);
        $apiSource->setPermissions([
            'blog:list',
            'order:delete',
        ]);

        static::assertTrue($apiSource->isAllowed('blog:list'));
        static::assertTrue($apiSource->isAllowed('order:delete'));

        static::assertFalse($apiSource->isAllowed('blog:delete'));
        static::assertFalse($apiSource->isAllowed('order:list'));
    }
}
