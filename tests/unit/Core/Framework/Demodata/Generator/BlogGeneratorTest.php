<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Demodata\Generator;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Demodata\DemodataContext;
use Contena\Core\Framework\Demodata\Faker\Commerce;
use Contena\Core\Framework\Demodata\Generator\BlogGenerator;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(BlogGenerator::class)]
class BlogGeneratorTest extends TestCase
{
    public function testBlogGeneration(): void
    {
        $blogCount = 41;

        $tagIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $ruleIds = [
            Uuid::randomHex(),
        ];

        $manufacturerIds = [
            Uuid::randomHex(),
        ];

        $channelIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $properties = [
            [
                'id' => Uuid::randomHex(),
                'property_group_id' => Uuid::randomHex(),
            ],
        ];

        $categoryIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $instantDeliveryId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(static function () use ($channelIds, $properties, $categoryIds) {
                $sqlStatement = \func_get_arg(0);

                if (\str_contains($sqlStatement, 'channel')) {
                    return \array_map(static fn (string $id) => ['id' => $id], $channelIds);
                }

                if (\str_contains($sqlStatement, 'property_group_option')) {
                    return $properties;
                }

                if (\str_contains($sqlStatement, 'category')) {
                    return \array_map(static fn (string $id) => ['id' => $id], $categoryIds);
                }

                return null;
            });
        $connection->method('fetchFirstColumn')->willReturn($ruleIds, $manufacturerIds, $tagIds);
        $connection->method('fetchOne')->willReturn($instantDeliveryId);

        $registry = static::createStub(DefinitionInstanceRegistry::class);

        $taxEntity = (new TaxEntity())
            ->assign([
                '_uniqueIdentifier' => 'tax_0',
                'taxRate' => 10,
                'id' => Uuid::randomHex(),
            ]);

        $taxRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'tax',
                1,
                new TaxCollection([$taxEntity]),
                null,
                new Criteria(),
                Context::createDefaultContext(),
            ),
        ]);

        $mediaIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $mediaBlogDownloadIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];

        $mediaRepository = new StaticEntityRepository([
            $mediaIds,
            $mediaBlogDownloadIds,
        ]);

        $blogRepository = new StaticEntityRepository([]);

        $registry->method('getRepository')->willReturnCallback(static function () use ($taxRepository, $mediaRepository, &$blogRepository) {
            $entityName = \func_get_arg(0);

            return match ($entityName) {
                'tax' => $taxRepository,
                'media' => $mediaRepository,
                'blog' => $blogRepository,
                default => null,
            };
        });

        $inheritanceUpdater = $this->createMock(InheritanceUpdater::class);
        $inheritanceUpdater->expects($this->exactly(3))->method('update');

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects($this->exactly(3))->method('update');

        $blogGenerator = new BlogGenerator($connection, $registry, $inheritanceUpdater, $statesUpdater);

        $generator = Factory::create();
        $generator->addProvider(new Commerce($generator));

        $context = static::createStub(DemodataContext::class);
        $context->method('getFaker')->willReturn($generator);

        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('progressStart')->with($blogCount);
        $io->expects($this->exactly((int) ($blogCount / 20)))->method('progressAdvance');
        $io->expects($this->once())->method('progressFinish');

        $context->method('getConsole')->willReturn($io);

        $blogGenerator->generate($blogCount, $context);

        $blogs = [];

        foreach ($blogRepository->creates as $blogBatches) {
            $blogs = [...$blogs, ...\array_values($blogBatches)];
        }

        static::assertCount($blogCount, $blogs);

        foreach ($blogs as $blog) {
            static::assertNotNull($blog['id']);
            static::assertIsString($blog['blogNumber']);
            static::assertStringStartsWith('SW_', $blog['blogNumber']);
            static::assertIsArray($blog['price']);
            static::assertIsArray($blog['price'][0]);
            static::assertIsString($blog['price'][0]['currencyId']);
            static::assertIsFloat($blog['price'][0]['gross']);
            static::assertIsFloat($blog['price'][0]['net']);
            static::assertIsBool($blog['price'][0]['linked']);
            static::assertIsArray($blog['purchasePrices']);
            static::assertIsArray($blog['purchasePrices'][0]);
            static::assertIsString($blog['purchasePrices'][0]['currencyId']);
            static::assertIsFloat($blog['purchasePrices'][0]['gross']);
            static::assertIsFloat($blog['purchasePrices'][0]['net']);
            static::assertIsBool($blog['purchasePrices'][0]['linked']);
            static::assertIsString($blog['name']);
            static::assertIsString($blog['description']);
            static::assertIsString($blog['taxId']);
            static::assertIsString($blog['manufacturerId']);
            static::assertIsBool($blog['active']);
            static::assertIsInt($blog['height']);
            static::assertIsInt($blog['width']);
            static::assertIsArray($blog['categories']);

            foreach ($blog['categories'] as $category) {
                static::assertContains($category['id'], $categoryIds);
            }

            static::assertIsArray($blog['tags']);

            foreach ($blog['tags'] as $tag) {
                static::assertContains($tag['id'], $tagIds);
            }

            static::assertIsInt($blog['stock']);
            static::assertIsArray($blog['prices']);

            if ($blog['prices'] !== []) {
                foreach ($blog['prices'] as $price) {
                    static::assertContains($price['ruleId'], $ruleIds);
                    static::assertIsInt($price['quantityStart']);

                    if (\array_key_exists('quantityEnd', $price)) {
                        static::assertIsInt($price['quantityEnd']);
                    }

                    static::assertIsArray($price['price']);
                    static::assertIsArray($price['price'][0]);
                    static::assertIsString($price['price'][0]['currencyId']);
                    static::assertIsFloat($price['price'][0]['gross']);
                    static::assertIsFloat($price['price'][0]['net']);
                    static::assertFalse($price['price'][0]['linked']);
                }
            }

            static::assertIsArray($blog['visibilities']);

            foreach ($blog['visibilities'] as $visibility) {
                static::assertContains($visibility['channelId'], $channelIds);
                static::assertSame(30, $visibility['visibility']);
            }

            static::assertIsArray($blog['cover']);
            static::assertIsString($blog['cover']['mediaId']);
            static::assertContains($blog['cover']['mediaId'], $mediaIds);
            static::assertIsArray($blog['media']);

            foreach ($blog['media'] as $media) {
                static::assertContains($media['mediaId'], $mediaIds);
            }

            static::assertIsArray($blog['properties']);
            static::assertIsArray($blog['properties'][0]);
            static::assertIsString($blog['properties'][0]['id']);
            static::assertSame($properties[0]['id'], $blog['properties'][0]['id']);

            if (\array_key_exists('children', $blog)) {
                static::assertIsArray($blog['children']);
                static::assertIsArray($child = $blog['children'][0]);

                static::assertIsString($child['id']);
                static::assertIsString($child['blogNumber']);
                static::assertStringStartsWith('SW_', $blog['blogNumber']);
                static::assertIsArray($child['price']);
                static::assertIsArray($child['price'][0]);
                static::assertIsString($child['price'][0]['currencyId']);
                static::assertIsFloat($child['price'][0]['gross']);
                static::assertIsFloat($child['price'][0]['net']);
                static::assertIsBool($child['price'][0]['linked']);
                static::assertIsBool($child['active']);
                static::assertIsInt($child['stock']);
                static::assertIsArray($child['prices']);

                if ($child['prices'] !== []) {
                    foreach ($child['prices'] as $price) {
                        static::assertContains($price['ruleId'], $ruleIds);
                        static::assertIsInt($price['quantityStart']);

                        if (\array_key_exists('quantityEnd', $price)) {
                            static::assertIsInt($price['quantityEnd']);
                        }

                        static::assertIsArray($price['price']);
                        static::assertIsArray($price['price'][0]);
                        static::assertIsString($price['price'][0]['currencyId']);
                        static::assertIsFloat($price['price'][0]['gross']);
                        static::assertIsFloat($price['price'][0]['net']);
                        static::assertFalse($price['price'][0]['linked']);
                    }
                }

                static::assertIsArray($child['options']);
                static::assertIsArray($child['options'][0]);
                static::assertIsString($child['options'][0]['id']);
                static::assertSame($properties[0]['id'], $child['options'][0]['id']);
            }

            if (\array_key_exists('configuratorSettings', $blog)) {
                static::assertIsArray($blog['configuratorSettings']);
                static::assertIsArray($blog['configuratorSettings'][0]);
                static::assertIsString($blog['configuratorSettings'][0]['optionId']);
                static::assertSame($properties[0]['id'], $blog['configuratorSettings'][0]['optionId']);
            }

            if (\array_key_exists('downloads', $blog)) {
                static::assertIsArray($blog['downloads']);

                foreach ($blog['downloads'] as $download) {
                    static::assertIsString($download['id']);
                    static::assertContains($download['mediaId'], $mediaBlogDownloadIds);
                    static::assertIsInt($download['position']);
                }

                static::assertSame(1, $blog['maxPurchase']);
                static::assertSame($instantDeliveryId, $blog['deliveryTimeId']);
            }
        }
    }
}
