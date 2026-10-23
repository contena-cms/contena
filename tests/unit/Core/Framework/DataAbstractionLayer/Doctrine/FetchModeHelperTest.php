<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(FetchModeHelper::class)]
class FetchModeHelperTest extends TestCase
{
    public function testGroup(): void
    {
        $result = FetchModeHelper::group([
            ['wh' => 'wh-1', 'blog' => 'PROD-1', 'stock' => '10'],
            ['wh' => 'wh-1', 'blog' => 'PROD-2', 'stock' => '5'],
            ['wh' => 'wh-2', 'blog' => 'PROD-3', 'stock' => '20'],
        ]);

        static::assertSame(
            [
                'wh-1' => [
                    ['blog' => 'PROD-1', 'stock' => '10'],
                    ['blog' => 'PROD-2', 'stock' => '5'],
                ],
                'wh-2' => [
                    ['blog' => 'PROD-3', 'stock' => '20'],
                ],
            ],
            $result
        );
    }

    public function testGroupWithMapper(): void
    {
        $result = FetchModeHelper::group(
            [
                ['id' => 'some-set-1', 'entity_name' => 'blog'],
                ['id' => 'some-set-1', 'entity_name' => 'user'],
                ['id' => 'some-set-2', 'entity_name' => 'blog'],
            ],
            static fn (array $row) => $row['entity_name']
        );

        static::assertSame(
            [
                'some-set-1' => ['blog', 'user'],
                'some-set-2' => ['blog'],
            ],
            $result
        );
    }
}
