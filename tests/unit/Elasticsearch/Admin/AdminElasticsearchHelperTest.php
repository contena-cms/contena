<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Elasticsearch\Admin\AdminElasticsearchHelper;

/**
 * @internal
 */
#[CoversClass(AdminElasticsearchHelper::class)]
class AdminElasticsearchHelperTest extends TestCase
{
    #[DataProvider('searchHelperProvider')]
    public function testSearchHelper(bool $adminEsEnabled, bool $refreshIndices, string $adminIndexPrefix): void
    {
        $searchHelper = new AdminElasticsearchHelper($adminEsEnabled, $refreshIndices, $adminIndexPrefix, 'test', true, new NullLogger());

        static::assertSame($adminEsEnabled, $searchHelper->isEnabled());
        static::assertSame($refreshIndices, $searchHelper->getRefreshIndices());
        static::assertSame($adminIndexPrefix, $searchHelper->getPrefix());
        static::assertSame($adminIndexPrefix . '-promotion-listing', $searchHelper->getIndex('promotion-listing'));
    }

    public function testSetEnable(): void
    {
        $searchHelper = new AdminElasticsearchHelper(false, false, 'ct-admin', 'test', true, new NullLogger());

        static::assertFalse($searchHelper->isEnabled());
        static::assertFalse($searchHelper->getRefreshIndices());
        static::assertSame('ct-admin', $searchHelper->getPrefix());
        static::assertSame('ct-admin-promotion-listing', $searchHelper->getIndex('promotion-listing'));

        $searchHelper->setEnabled(true);

        static::assertTrue($searchHelper->isEnabled());
    }

    public static function searchHelperProvider(): \Generator
    {
        yield 'Not enable ES and not refresh indices' => [
            false,
            false,
            'ct-admin',
        ];

        yield 'Enable ES and not refresh indices' => [
            true,
            false,
            'ct-admin',
        ];

        yield 'Enable ES and refresh indices' => [
            true,
            true,
            'ct-admin',
        ];
    }
}
