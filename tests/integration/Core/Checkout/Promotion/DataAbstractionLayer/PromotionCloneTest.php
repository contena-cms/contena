<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Promotion\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Checkout\Promotion\PromotionEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class PromotionCloneTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCloneResetsRedemptionCounters(): void
    {
        $context = Context::createDefaultContext();
        $sourceId = Uuid::randomHex();
        $cloneId = Uuid::randomHex();
        $userId = Uuid::randomHex();

        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $promotionRepository->create([[
            'id' => $sourceId,
            'name' => 'Used promotion',
            'orderCount' => 3,
            'ordersPerUserCount' => [$userId => 2],
        ]], $context);

        $promotionRepository->clone($sourceId, $context, $cloneId);

        $clone = $promotionRepository->search(new Criteria([$cloneId]), $context)->getEntities()->first();
        static::assertInstanceOf(PromotionEntity::class, $clone);
        static::assertSame(0, $clone->getOrderCount());
        static::assertNull($clone->getOrdersPerUserCount());
    }
}
