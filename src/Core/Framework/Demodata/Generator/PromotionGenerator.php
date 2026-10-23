<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\PromotionDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
class PromotionGenerator implements DemodataGeneratorInterface
{
    private SymfonyStyle $io;

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry
    ) {
    }

    public function getDefinition(): string
    {
        return PromotionDefinition::class;
    }

    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $this->io = $context->getConsole();

        $this->createPromotions($context->getContext(), $numberOfItems);
    }

    private function createPromotions(Context $context, int $count): void
    {
        $channels = $this->getChannels();

        $this->io->progressStart($count);

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $promotion = $this->createPromotion($channels);

            $payload[] = $promotion;

            if (\count($payload) >= 20) {
                $this->io->progressAdvance(\count($payload));
                $this->write($payload, $context);
                $payload = [];
            }
        }

        if ($payload !== []) {
            $this->write($payload, $context);
        }

        $this->io->progressFinish();
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function write(array $payload, Context $context): void
    {
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $this->registry->getRepository('promotion')->create($payload, $context);

        $context->removeState(EntityIndexerRegistry::DISABLE_INDEXING);
    }

    /**
     * @param array<array{channelId: string, priority: 1}> $channels
     *
     * @return array<string, mixed>
     */
    private function createPromotion(array $channels): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => $this->faker->format('blogName'),
            'active' => true,
            'useSetGroups' => false,
            'channels' => $channels,
            'code' => $this->faker->unique()->format('promotionCode'),
            'useCodes' => true,
            'discounts' => $this->createDiscounts(),
            'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createDiscounts(): array
    {
        $discounts = [];
        $count = $this->faker->randomDigit() / 3;

        for ($i = 0; $i <= $count; ++$i) {
            $scope = $this->faker->randomElement([PromotionDiscountEntity::SCOPE_CART, PromotionDiscountEntity::SCOPE_DELIVERY]);
            $type = $this->faker->randomElement([PromotionDiscountEntity::TYPE_ABSOLUTE, PromotionDiscountEntity::TYPE_PERCENTAGE]);
            $value = $this->faker->randomFloat(2, 0.01, 100);
            if ($type === PromotionDiscountEntity::TYPE_PERCENTAGE || $scope === PromotionDiscountEntity::SCOPE_DELIVERY) {
                $value /= 10;
            }

            $discounts[] = [
                'scope' => $scope,
                'type' => $type,
                'value' => $value,
                'considerAdvancedRules' => false,
            ];
        }

        return $discounts;
    }

    /**
     * @return array<array{channelId: string, priority: 1}>
     */
    private function getChannels(): array
    {
        $ids = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM `channel` LIMIT 100');

        return array_map(static fn ($id) => ['channelId' => $id['id'], 'priority' => 1], $ids);
    }
}
