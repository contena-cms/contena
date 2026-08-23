<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Elasticsearch\Blog\ElasticsearchBlogDefinition;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Contena\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Contena\Elasticsearch\Framework\ElasticsearchRegistry;

/**
 * @internal
 */
#[CoversClass(ElasticsearchOutdatedIndexDetector::class)]
class ElasticsearchOutdatedIndexDetectorTest extends TestCase
{
    public function testUsesChunks(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static fn () => [
                Uuid::randomHex() => [
                    'aliases' => [
                        'test',
                    ],
                    'settings' => [
                        'index' => [
                            'provided_name' => Uuid::randomHex(),
                        ],
                    ],
                ],
                Uuid::randomHex() => [
                    'aliases' => [],
                    'settings' => [
                        'index' => [
                            'provided_name' => Uuid::randomHex(),
                        ],
                    ],
                ],
            ]);

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $definition = static::createStub(ElasticsearchBlogDefinition::class);

        $registry = static::createStub(ElasticsearchRegistry::class);
        $registry->method('getDefinitions')->willReturn([$definition, $definition]);

        $makeLanguage = static fn () => new LanguageEntity()->assign(['id' => Uuid::randomHex()]);

        $collection = new EntitySearchResult(1, new LanguageCollection([$makeLanguage(), $makeLanguage(), $makeLanguage()]), null, new Criteria(), Context::createDefaultContext());

        $repository = static::createStub(EntityRepository::class);
        $repository
            ->method('search')
            ->willReturn($collection);

        $esHelper = static::createStub(ElasticsearchHelper::class);

        $detector = new ElasticsearchOutdatedIndexDetector($client, $registry, $esHelper);
        $arr = $detector->get();
        static::assertNotNull($arr);
        static::assertCount(1, $arr);
        static::assertCount(2, $detector->getAllUsedIndices());
    }

    public function testDoesNothingWithoutIndices(): void
    {
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->exactly(0))
            ->method('get')
            ->willReturnCallback(static fn () => []);

        $client = static::createStub(Client::class);
        $client->method('indices')->willReturn($indices);

        $registry = static::createStub(ElasticsearchRegistry::class);

        $esHelper = static::createStub(ElasticsearchHelper::class);

        $detector = new ElasticsearchOutdatedIndexDetector($client, $registry, $esHelper);
        static::assertEmpty($detector->get());
    }
}
