<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\SearchKeyword;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\SearchKeyword\KeywordLoader;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(KeywordLoader::class)]
class KeywordLoaderTest extends TestCase
{
    public function testFetch(): void
    {
        $tenantId = Uuid::randomHex();
        $slops = ['foo', 'bar'];
        $tokenSlops = [[
            'normal' => [$slops[0]],
            'reversed' => [$slops[1]],
        ]];

        $connection = static::createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with(static::anything(), static::callback(static function (array $params) use ($slops, $tenantId): bool {
                foreach ($slops as $slop) {
                    static::assertContains($slop, $params);
                }
                static::assertContains(Uuid::fromHexToBytes($tenantId), $params);

                return true;
            }));

        new KeywordLoader($connection)->fetch($tokenSlops, Context::createTenantContext($tenantId));
    }
}
