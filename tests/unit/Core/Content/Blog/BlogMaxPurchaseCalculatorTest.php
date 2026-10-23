<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogMaxPurchaseCalculator;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(BlogMaxPurchaseCalculator::class)]
class BlogMaxPurchaseCalculatorTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $configService = static::createStub(SystemConfigService::class);
        $configService->method('getInt')->willReturn(10);
        $this->service = new BlogMaxPurchaseCalculator($configService);
    }

    /**
     * @param array<string, int|bool|string> $entityData
     */
    #[DataProvider('cases')]
    public function testCalculate(array $entityData, int $expected): void
    {
        $entity = new PartialEntity();
        $entity->assign($entityData);

        static::assertSame($expected, $this->service->calculate($entity, static::createStub(ChannelContext::class)));
    }

    public static function cases(): \Generator
    {
        yield 'empty' => [
            [
            ],
            10,
        ];

        yield 'max_in_entity' => [
            [
                'maxPurchase' => 5,
            ],
            5,
        ];

        yield 'purchase_steps' => [
            [
                'maxPurchase' => 5,
                'minPurchase' => 2,
                'purchaseSteps' => 2,
            ],
            4,
        ];

        yield 'available_stock without closeout' => [
            [
                'maxPurchase' => 5,
                'minPurchase' => 2,
                'purchaseSteps' => 2,
                'availableStock' => 2,
                'stock' => 2,
                'isCloseout' => false,
            ],
            4,
        ];

        yield 'available_stock only when closeout' => [
            [
                'maxPurchase' => 5,
                'minPurchase' => 2,
                'purchaseSteps' => 2,
                'availableStock' => 2,
                'stock' => 2,
                'isCloseout' => true,
            ],
            2,
        ];

        yield 'digital blog without maxPurchase falls back to system config like other blogs' => [
            [
                'type' => BlogDefinition::TYPE_DIGITAL,
            ],
            10,
        ];

        yield 'digital blog with maxPurchase 1 is limited to one unit' => [
            [
                'type' => BlogDefinition::TYPE_DIGITAL,
                'maxPurchase' => 1,
            ],
            1,
        ];

        yield 'digital blog allows a configured maxPurchase above 1' => [
            [
                'type' => BlogDefinition::TYPE_DIGITAL,
                'maxPurchase' => 5,
            ],
            5,
        ];
    }
}
