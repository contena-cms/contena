<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataException;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class OrderGenerator implements DemodataGeneratorInterface
{
    /**
     * @var array<string, ChannelContext>
     */
    private array $contexts = [];

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractChannelContextFactory $contextFactory,
        private readonly CartService $cartService,
        private readonly OrderConverter $orderConverter,
        private readonly EntityWriterInterface $writer,
        private readonly OrderDefinition $orderDefinition,
        private readonly CartCalculator $cartCalculator
    ) {
    }

    public function getDefinition(): string
    {
        return OrderDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $channelIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM channel');
        if ($channelIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }

        $paymentMethodIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM payment_method');
        $blogIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM `blog` ORDER BY RAND() LIMIT 1000');
        $promotionCodes = $this->connection->fetchFirstColumn('SELECT `code` FROM `promotion` ORDER BY RAND() LIMIT 1000');
        $userIds = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM user LIMIT 10');
        $tags = $this->getTagIds();
        $writeContext = WriteContext::createFromContext($context->getContext());

        $context->getConsole()->progressStart($numberOfItems);

        $blogLineItems = array_map(
            fn ($blogId) => (new LineItem($blogId, LineItem::BLOG_LINE_ITEM_TYPE, $blogId, $this->faker->randomDigit() + 1))
                ->setStackable(true)
                ->setRemovable(true),
            $blogIds
        );
        $promotionLineItems = array_map(
            static function ($promotionCode) {
                $uniqueKey = PromotionItemBuilder::PLACEHOLDER_PREFIX . $promotionCode;

                return (new LineItem(Uuid::fromStringToHex($uniqueKey), LineItem::PROMOTION_LINE_ITEM_TYPE))
                    ->setLabel($uniqueKey)
                    ->setGood(false)
                    ->setReferencedId($promotionCode)
                    ->setPriceDefinition(new PercentagePriceDefinition(0));
            },
            $promotionCodes
        );

        $orders = [];

        for ($i = 1; $i <= $numberOfItems; ++$i) {
            $userId = $context->getFaker()->randomElement($userIds);

            $channelContext = $this->getContext($userId, $channelIds, $paymentMethodIds);

            $cart = $this->cartService->createNew($channelContext->getToken());
            foreach ($this->faker->randomElements($blogLineItems, random_int(3, 5)) as $lineItem) {
                $cart->add($lineItem);
            }

            if ($promotionLineItems) {
                foreach ($this->faker->randomElements($promotionLineItems, random_int(0, 3)) as $lineItem) {
                    $cart->add($lineItem);
                }
            }

            $cart = $this->cartCalculator->calculate($cart, $channelContext);
            $tempOrder = $this->orderConverter->convertToOrder($cart, $channelContext, new OrderConversionContext());

            $randomDate = $context->getFaker()->dateTimeBetween('-2 years');

            $tempOrder['orderDateTime'] = $randomDate->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $tempOrder['tags'] = $this->getTags($tags);
            $tempOrder['customFields'] = [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true];

            $orders[] = $tempOrder;

            if (\count($orders) >= 20) {
                $this->writer->upsert($this->orderDefinition, $orders, $writeContext);
                $context->getConsole()->progressAdvance(\count($orders));
                $orders = [];
            }
        }

        if ($orders !== []) {
            $this->writer->upsert($this->orderDefinition, $orders, $writeContext);
        }

        $context->getConsole()->progressFinish();
    }

    /**
     * @param list<string> $tags
     *
     * @return list<array{id: string}>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if ($tags !== []) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit());

            if ($chosenTags !== []) {
                $tagAssignments = array_map(
                    static fn (string $id) => ['id' => $id],
                    $chosenTags
                );
            }
        }

        return array_values($tagAssignments);
    }

    /**
     * @return list<string>
     */
    private function getTagIds(): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM tag LIMIT 500');
    }

    /**
     * @param non-empty-list<string> $channelIds
     * @param array<string> $paymentMethodIds
     */
    private function getContext(string $userId, array $channelIds, array $paymentMethodIds = []): ChannelContext
    {
        if (isset($this->contexts[$userId])) {
            return $this->contexts[$userId];
        }

        $options = [
            ChannelContextService::CUSTOMER_ID => $userId,
        ];

        if ($paymentMethodIds !== []) {
            $options[ChannelContextService::PAYMENT_METHOD_ID] = $this->faker->randomElement($paymentMethodIds);
        }

        $context = $this->contextFactory->create(Uuid::randomHex(), $channelIds[array_rand($channelIds)], $options);

        return $this->contexts[$userId] = $context;
    }
}
