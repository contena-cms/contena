<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Promotion\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Contena\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexer;
use Contena\Core\Checkout\Promotion\DataAbstractionLayer\PromotionIndexingMessage;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Checkout\Promotion\PromotionDefinition;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionIndexerTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testPromotionIndexerUpdateReturnNullIfGeneratingCode(): void
    {
        $indexer = static::getContainer()->get(PromotionIndexer::class);

        $channelContext = $this->createChannelContext();

        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');

        /** @var EntityRepository<PromotionIndividualCodeCollection> $promotionIndividualRepository */
        $promotionIndividualRepository = static::getContainer()->get('promotion_individual_code.repository');

        $voucherA = $this->ids->create('voucherA');

        $writtenEvent = $this->createPromotion($voucherA, $voucherA, $promotionRepository, $channelContext);
        $promotionEvent = $writtenEvent->getEventByEntityName(PromotionDefinition::ENTITY_NAME);

        static::assertNotNull($promotionEvent);
        static::assertArrayHasKey(0, $promotionEvent->getWriteResults());
        $promotionId = $promotionEvent->getWriteResults()[0]->getPayload()['id'];

        $admin_userId = Uuid::randomHex();
        $origin = new AdminApiSource($admin_userId);
        $origin->setIsAdmin(true);
        $context = Context::createDefaultContext($origin);

        $event = $this->createIndividualCode($promotionId, 'CODE-1', $promotionIndividualRepository, $context);

        $result = $indexer->update($event);

        static::assertNull($result);
    }

    public function testPromotionIndexerUpdateReturnPromotionIndexingMessage(): void
    {
        $indexer = static::getContainer()->get(PromotionIndexer::class);

        $channelContext = $this->createChannelContext();

        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');

        $voucherA = $this->ids->create('voucherA');

        $writtenEvent = $this->createPromotion($voucherA, $voucherA, $promotionRepository, $channelContext);

        $result = $indexer->update($writtenEvent);

        static::assertInstanceOf(PromotionIndexingMessage::class, $result);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createChannelContext(array $options = []): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $channelContextFactory->create($token, TestDefaults::CHANNEL, $options);
    }
}
