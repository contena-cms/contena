<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use OpenSearch\Exception\BadRequestHttpException;
use OpenSearch\Exception\NotFoundHttpException;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Context;
use Contena\Elasticsearch\Blog\ElasticsearchBlogDefinition;
use Contena\Elasticsearch\Blog\ElasticsearchBlogException;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Contena\Elasticsearch\Framework\ElasticsearchRegistry;
use Contena\Elasticsearch\Framework\Indexing\IndexMappingProvider;
use Contena\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Contena\Elasticsearch\Framework\SystemUpdateListener;

/**
 * @internal
 */
#[CoversClass(IndexMappingUpdater::class)]
class IndexMappingUpdaterTest extends TestCase
{
    public function testUpdateWithoutIndexingEnabled(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(false);

        $registry = new ElasticsearchRegistry([
            static::createStub(ElasticsearchBlogDefinition::class),
        ]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->never())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ]);

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->never())->method('set');

        $indexMappingProvider = $this->createMock(IndexMappingProvider::class);
        $indexMappingProvider
            ->expects($this->never())
            ->method('build')
            ->willReturn(['foo' => '1']);

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdate(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $elasticsearchHelper->method('getIndexName')->willReturn('index');

        $registry = new ElasticsearchRegistry([
            static::createStub(ElasticsearchBlogDefinition::class),
        ]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ]);

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            static::createStub(AbstractKeyValueStorage::class),
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchBlogDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new BlogDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequestHttpException('can\'t merge a non object mapping [completion] with an object mapping'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $elasticsearchHelper->expects($this->once())->method('logAndThrowException')->with(
            static::callback(static function (ElasticsearchBlogException $exception) {
                return $exception->getMessage() === 'One or more fields already exist in the index with different types. Please reset the index and rebuild it.';
            }),
        );

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->once())
            ->method('set')
            ->with(
                SystemUpdateListener::CONFIG_KEY,
                ['blog'],
            );

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage,
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithMissingIndexError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchBlogDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new BlogDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new NotFoundHttpException('no such index [index]'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $elasticsearchHelper->expects($this->once())->method('logAndThrowException')->with(
            static::callback(static function (NotFoundHttpException $exception) {
                return $exception->getMessage() === 'no such index [index]';
            }),
        );

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->never())->method('set');

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage,
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithObjectNestingChangeError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchBlogDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new BlogDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequestHttpException('illegal_argument_exception: cannot change object mapping from non-nested to nested'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $elasticsearchHelper->expects($this->once())->method('logAndThrowException')->with(
            static::callback(static function (ElasticsearchBlogException $exception) {
                return $exception->getMessage() === 'One or more fields already exist in the index with different types. Please reset the index and rebuild it.';
            }),
        );

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->once())
            ->method('set')
            ->with(
                SystemUpdateListener::CONFIG_KEY,
                ['blog'],
            );

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage,
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithConflictedMappingError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchBlogDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new BlogDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequestHttpException('Mapper for [name.01985ba1826270e4b8ea5da15a05c7bf.search] conflicts with existing mapper:\n\tCannot update parameter [analyzer] from [sw_czech_analyzer] to [sw_whitespace_analyzer].'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $elasticsearchHelper->expects($this->once())->method('logAndThrowException')->with(
            static::callback(static function (ElasticsearchBlogException $exception) {
                return $exception->getMessage() === 'One or more fields already exist in the index with different types. Please reset the index and rebuild it.';
            }),
        );

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->once())
            ->method('set')
            ->with(
                SystemUpdateListener::CONFIG_KEY,
                ['blog'],
            );

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage,
        );

        $updater->update(Context::createDefaultContext());
    }

    public function testUpdateWithAnalyzerNotConfiguredError(): void
    {
        $elasticsearchHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchHelper->method('getIndexName')->willReturn('index');
        $elasticsearchHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $definition = static::createStub(ElasticsearchBlogDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new BlogDefinition());

        $registry = new ElasticsearchRegistry([$definition]);

        $client = static::createStub(Client::class);
        $indicesNamespace = $this->createMock(IndicesNamespace::class);
        $indicesNamespace
            ->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'index',
                'body' => [
                    'foo' => '1',
                ],
            ])->willThrowException(new BadRequestHttpException('mapper_parsing_exception: analyzer [sw_whitespace_technical_term_search_analyzer] has not been configured in mappings'));

        $client
            ->method('indices')
            ->willReturn($indicesNamespace);

        $indexMappingProvider = static::createStub(IndexMappingProvider::class);
        $indexMappingProvider
            ->method('build')
            ->willReturn(['foo' => '1']);

        $elasticsearchHelper->expects($this->once())->method('logAndThrowException')->with(
            static::callback(static function (ElasticsearchBlogException $exception) {
                return $exception->getMessage() === 'One or more fields already exist in the index with different types. Please reset the index and rebuild it.';
            }),
        );

        $storage = $this->createMock(AbstractKeyValueStorage::class);
        $storage->expects($this->once())
            ->method('set')
            ->with(
                SystemUpdateListener::CONFIG_KEY,
                ['blog'],
            );

        $updater = new IndexMappingUpdater(
            $registry,
            $elasticsearchHelper,
            $client,
            $indexMappingProvider,
            $storage,
        );

        $updater->update(Context::createDefaultContext());
    }
}
