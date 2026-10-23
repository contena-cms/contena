<?php declare(strict_types=1);

namespace Contena\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Generator;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\DemodataException;
use Contena\Core\Framework\Demodata\DemodataGeneratorInterface;
use Contena\Core\Framework\Demodata\DemodataService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxEntity;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
class BlogGenerator implements DemodataGeneratorInterface
{
    private SymfonyStyle $io;

    private Generator $faker;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $registry,
        private readonly InheritanceUpdater $updater,
        private readonly ?StatesUpdater $statesUpdater = null
    ) {
    }

    public function getDefinition(): string
    {
        return BlogDefinition::class;
    }

    /**
     * @param mixed[] $options
     */
    public function generate(int $numberOfItems, DemodataContext $context, array $options = []): void
    {
        $this->faker = $context->getFaker();
        $this->io = $context->getConsole();

        $this->createBlogs($context->getContext(), $numberOfItems);
    }

    private function createBlogs(Context $context, int $count): void
    {
        $visibilities = $this->buildVisibilities();

        $taxes = $this->getTaxes($context);

        if ($taxes->count() === 0) {
            throw DemodataException::wrongExecutionOrder();
        }

        $properties = $this->getProperties();

        $this->io->progressStart($count);

        $mediaIds = $this->getMediaIds();
        $downloadMediaIds = $this->getMediaIds('blog_download');

        $ruleIds = $this->getIds('rule');

        $manufacturers = $this->getIds('blog_manufacturer');

        $tags = $this->getIds('tag');

        $instantDeliveryId = $this->getInstantDeliveryId();

        $combinations = [];
        for ($i = 0; $i <= 20; ++$i) {
            $combinations[] = $this->buildCombinations($properties);
        }

        $max = max(min($count / 3, 200), 5);
        $prices = [];
        for ($i = 0; $i <= $max; ++$i) {
            $prices[] = $this->createPrices($ruleIds);
        }

        $payload = [];
        for ($i = 0; $i < $count; ++$i) {
            $blog = $this->createSimpleBlog($taxes, $manufacturers, $tags);

            $blog['prices'] = $this->faker->randomElement($prices);

            $blog['visibilities'] = $visibilities;

            if ($mediaIds) {
                $blog['cover'] = ['mediaId' => Random::getRandomArrayElement($mediaIds)];

                $blog['media'] = array_map(static fn (string $id): array => ['mediaId' => $id], $this->faker->randomElements($mediaIds, random_int(2, 5)));
            }

            $blog['properties'] = $this->buildProperties($properties);

            if ($i % 40 === 0) {
                $combination = $this->faker->randomElement($combinations);
                $blog = [...$blog, ...$this->buildVariants($combination, $prices, $taxes)];
            } elseif ($i % 20 === 0) {
                $blog = [...$blog, ...$this->buildDownloads($downloadMediaIds, $instantDeliveryId)];
            }

            $payload[] = $blog;

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
     * @param array<string, list<string>> $properties
     *
     * @return array<array<string, mixed>>
     */
    private function buildCombinations(array $properties): array
    {
        $properties = $this->faker->randomElements($properties, random_int(min(\count($properties), 1), min(\count($properties), 4)));

        $mapped = [];
        // reduce permutation count
        foreach ($properties as $index => $values) {
            $permutations = is_countable($values) ? \count($values) : 0;
            if ($permutations > 4) {
                $permutations = random_int(2, 4);
            }
            $mapped[$index] = $this->faker->randomElements($values, $permutations);
        }
        $properties = $mapped;

        $result = [[]];
        foreach ($properties as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $combinations
     * @param list<list<array<string, mixed>>> $prices
     *
     * @return array<string, mixed>
     */
    private function buildVariants(array $combinations, array $prices, TaxCollection $taxes): array
    {
        $configurator = [];

        $variants = [];
        foreach ($combinations as $options) {
            $price = $this->faker->randomFloat(2, 1, 1000);
            $tax = $this->getRandomTax($taxes);
            $taxRate = 1 + ($tax->getTaxRate() / 100);

            $id = Uuid::randomHex();
            $variants[] = [
                'id' => $id,
                'blogNumber' => 'SW_' . $id,
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $taxRate, 'linked' => true]],
                'active' => true,
                'stock' => $this->faker->numberBetween(1, 50),
                'prices' => $this->faker->randomElement($prices),
                'options' => array_map(static fn ($id) => ['id' => $id], $options),
            ];

            $configurator = [...$configurator, ...array_values($options)];
        }

        return [
            'children' => $variants,
            'configuratorSettings' => array_map(static fn (string $id) => ['optionId' => $id], array_filter(array_unique($configurator))),
        ];
    }

    /**
     * @param list<string>|list<array<string, string>> $downloadMediaIds
     *
     * @return array{downloads: list<array{id: string, mediaId: string, position: int}>, type: BlogDefinition::TYPE_DIGITAL, maxPurchase: 1, deliveryTimeId: string|null}
     */
    private function buildDownloads(array $downloadMediaIds, ?string $instantDeliveryId): array
    {
        $mediaIds = $this->faker->randomElements($downloadMediaIds, random_int(1, 3), false);

        $downloads = [];
        foreach ($mediaIds as $position => $mediaId) {
            $downloads[] = [
                'id' => Uuid::randomHex(),
                'mediaId' => $mediaId,
                'position' => $position,
            ];
        }

        return [
            'downloads' => $downloads,
            'type' => BlogDefinition::TYPE_DIGITAL,
            'maxPurchase' => 1,
            'deliveryTimeId' => $instantDeliveryId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $payload
     */
    private function write(array $payload, Context $context): void
    {
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $this->registry->getRepository('blog')->create($payload, $context);

        $all = array_column($payload, 'id');
        foreach ($payload as $blog) {
            if (!isset($blog['children'])) {
                continue;
            }
            $all = [...$all, ...array_column($blog['children'], 'id')];
        }

        $this->updater->update(BlogDefinition::ENTITY_NAME, $all, $context);
        $this->statesUpdater?->update($all, $context);

        $context->removeState(EntityIndexerRegistry::DISABLE_INDEXING);
    }

    private function getTaxes(Context $context): TaxCollection
    {
        $taxRepository = $this->registry->getRepository('tax');

        $taxCollection = $taxRepository->search(new Criteria(), $context)->getEntities();
        \assert($taxCollection instanceof TaxCollection);

        return $taxCollection;
    }

    /**
     * @param array<string> $manufacturer
     * @param array<string> $tags
     *
     * @return array<string, mixed>
     */
    private function createSimpleBlog(
        TaxCollection $taxes,
        array $manufacturer,
        array $tags
    ): array {
        $price = $this->faker->randomFloat(2, 1, 1000);
        $purchasePrice = $this->faker->randomFloat(2, 1, 1000);
        $tax = $this->getRandomTax($taxes);
        $taxRate = 1 + ($tax->getTaxRate() / 100);

        return [
            'id' => Uuid::randomHex(),
            'type' => BlogDefinition::TYPE_PHYSICAL,
            'blogNumber' => 'SW_' . Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price / $taxRate, 'linked' => true]],
            'purchasePrices' => [['currencyId' => Defaults::CURRENCY, 'gross' => $purchasePrice, 'net' => $purchasePrice / $taxRate, 'linked' => true]],
            'name' => $this->faker->format('blogName'),
            'description' => $this->faker->text(),
            'taxId' => $tax->getId(),
            'manufacturerId' => $this->faker->randomElement($manufacturer),
            'active' => true,
            'height' => $this->faker->numberBetween(1, 1000),
            'width' => $this->faker->numberBetween(1, 1000),
            'categories' => $this->getCategoryIds(),
            'tags' => $this->getTags($tags),
            'stock' => $this->faker->numberBetween(1, 50),
            'customFields' => [DemodataService::DEMODATA_CUSTOM_FIELDS_KEY => true],
        ];
    }

    /**
     * @param array<string> $rules
     *
     * @return list<array<string, mixed>>
     */
    private function createPrices(array $rules): array
    {
        $prices = [];
        $rules = \array_slice(
            $rules,
            random_int(0, \max(\count($rules) - 3, 1)),
            random_int(1, 3)
        );

        $values = [];
        for ($i = 1; $i <= 200; ++$i) {
            $value = $this->faker->randomFloat(2, $i * 10, $i * 100);

            $values[] = [
                $value,
                round($value / 100 * random_int(50, 90), 2),
            ];
        }

        foreach ($rules as $ruleId) {
            $price = $this->faker->randomElement($values);

            $prices[] = [
                'ruleId' => $ruleId,
                'quantityStart' => 1,
                'quantityEnd' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price[0], 'net' => $price[0] / 119, 'linked' => false]],
            ];

            $prices[] = [
                'ruleId' => $ruleId,
                'quantityStart' => 11,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price[1], 'net' => $price[1] / 119, 'linked' => false]],
            ];
        }

        return $prices;
    }

    /**
     * @param array<string> $tags
     *
     * @return array<array{id: string}>
     */
    private function getTags(array $tags): array
    {
        $tagAssignments = [];

        if ($tags !== []) {
            $chosenTags = $this->faker->randomElements($tags, $this->faker->randomDigit(), false);

            if ($chosenTags !== []) {
                $tagAssignments = array_map(
                    static fn ($id) => ['id' => $id],
                    $chosenTags
                );
            }
        }

        return $tagAssignments;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getProperties(): array
    {
        $options = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id, LOWER(HEX(property_group_id)) as property_group_id FROM property_group_option LIMIT 5000');

        $grouped = [];
        foreach ($options as $option) {
            $grouped[(string) $option['property_group_id']][] = (string) $option['id'];
        }

        return $grouped;
    }

    /**
     * @return array<array{channelId: string, visibility: BlogVisibilityDefinition::VISIBILITY_ALL}>
     */
    private function buildVisibilities(): array
    {
        $ids = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) as id FROM channel LIMIT 100');

        return array_map(static fn ($id) => ['channelId' => $id['id'], 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL], $ids);
    }

    /**
     * @return list<string>
     */
    private function getMediaIds(string $entity = 'blog'): array
    {
        $repository = $this->registry->getRepository('media');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaFolder.defaultFolder.entity', $entity));
        $criteria->setLimit(500);

        return $repository->searchIds($criteria, Context::createDefaultContext())->getIds();
    }

    /**
     * @return list<string>
     */
    private function getIds(string $table): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) as id FROM ' . $table . ' LIMIT 500');
    }

    /**
     * @return list<array{id: string}>
     */
    private function getCategoryIds(): array
    {
        /** @var list<array{id: string}> $result */
        $result = $this->connection->fetchAllAssociative('
            SELECT LOWER(HEX(category.id)) as id
            FROM category
             LEFT JOIN blog_category pc
               ON pc.category_id = category.id
            WHERE category.child_count = 0
            GROUP BY category.id
            ORDER BY COUNT(pc.blog_id) ASC
            LIMIT ' . $this->faker->numberBetween(1, 3));

        return $result;
    }

    /**
     * @param array<string, list<string>> $properties
     *
     * @return array<array{id: string}>
     */
    private function buildProperties(array $properties): array
    {
        $blogProperties = [];
        foreach ($properties as $options) {
            $blogProperties = array_merge($blogProperties, $this->faker->randomElements($options, min(\count($options), 3)));
        }

        $blogProperties = \array_slice($blogProperties, 0, random_int(4, 10));

        return array_map(static fn ($config) => ['id' => (string) $config], $blogProperties);
    }

    private function getInstantDeliveryId(): ?string
    {
        $id = $this->connection->fetchOne('SELECT LOWER(HEX(delivery_time_id)) FROM delivery_time_translation WHERE `name` = "Instant download" LIMIT 1');

        return \is_string($id) ? $id : null;
    }

    private function getRandomTax(TaxCollection $taxes): TaxEntity
    {
        $taxIds = $taxes->getIds();
        if ($taxIds === []) {
            throw DemodataException::wrongExecutionOrder();
        }

        $tax = $taxes->get((string) array_rand($taxIds));
        if (!$tax instanceof TaxEntity) {
            throw DemodataException::wrongExecutionOrder();
        }

        return $tax;
    }
}
