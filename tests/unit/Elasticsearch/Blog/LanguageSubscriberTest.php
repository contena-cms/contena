<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Elasticsearch\Blog\ElasticsearchBlogDefinition;
use Contena\Elasticsearch\Blog\LanguageSubscriber;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Contena\Elasticsearch\Framework\ElasticsearchRegistry;

/**
 * @internal
 */
#[CoversClass(LanguageSubscriber::class)]
class LanguageSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            'language.written' => 'onLanguageWritten',
        ], LanguageSubscriber::getSubscribedEvents());
    }

    public function testOnLanguageWrittenWithoutEsEnabled(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(false);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            static::createStub(ElasticsearchRegistry::class),
            static::createStub(Client::class),
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects($this->never())
            ->method('getResults');

        $subscriber->onLanguageWritten($event);
    }

    public function testOnLanguageWrittenWithoutEsDefinition(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $writeResult = new EntityWriteResult(Uuid::randomHex(), [], CategoryDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            static::createStub(ElasticsearchRegistry::class),
            static::createStub(Client::class),
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects($this->once())
            ->method('getResults')->willReturn(new EntityWriteResultCollection([$writeResult]));

        $subscriber->onLanguageWritten($event);
    }

    public function testOnLanguageWrittenWithoutInsertOperation(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(true);

        $writeResult = new EntityWriteResult(Uuid::randomHex(), [], BlogDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE);
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $registry->expects($this->never())->method('getDefinitions')->willReturn([new BlogDefinition()]);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            $registry,
            static::createStub(Client::class),
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects($this->once())
            ->method('getResults')->willReturn(new EntityWriteResultCollection([$writeResult]));

        $subscriber->onLanguageWritten($event);
    }

    public function testOnLanguageWrittenWithoutExistingIndex(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(true);
        $esHelper->expects($this->once())->method('getIndexName')->willReturn('sw_blog');

        $writeResult = new EntityWriteResult(Uuid::randomHex(), [], BlogDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT);
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $esBlogDefinition = $this->createMock(ElasticsearchBlogDefinition::class);
        $esBlogDefinition->expects($this->once())->method('getEntityDefinition')->willReturn(new BlogDefinition());
        $registry->expects($this->once())->method('getDefinitions')->willReturn([$esBlogDefinition]);

        $client = static::createStub(Client::class);
        $namespace = $this->createMock(IndicesNamespace::class);
        $namespace->expects($this->once())->method('exists')->with(['index' => 'sw_blog'])->willReturn(false);

        $client->method('indices')->willReturn($namespace);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            $registry,
            $client,
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects($this->once())
            ->method('getResults')->willReturn(new EntityWriteResultCollection([$writeResult]));

        $subscriber->onLanguageWritten($event);
    }

    public function testOnLanguageWritten(): void
    {
        $esHelper = $this->createMock(ElasticsearchHelper::class);
        $esHelper->expects($this->once())->method('allowIndexing')->willReturn(true);
        $esHelper->expects($this->once())->method('getIndexName')->willReturn('sw_blog');

        $writeResult = new EntityWriteResult(Uuid::randomHex(), [], LanguageDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT);
        $client = static::createStub(Client::class);
        $registry = $this->createMock(ElasticsearchRegistry::class);
        $esBlogDefinition = $this->createMock(ElasticsearchBlogDefinition::class);
        $esBlogDefinition->expects($this->once())->method('getEntityDefinition')->willReturn(new BlogDefinition());
        $esBlogDefinition->expects($this->once())->method('getMapping')->willReturn([
            'properties' => [
                'field1' => 'test1',
                'field2' => 'test2',
            ],
        ]);
        $registry->expects($this->once())->method('getDefinitions')->willReturn([$esBlogDefinition]);

        $namespace = $this->createMock(IndicesNamespace::class);
        $namespace->expects($this->once())->method('putMapping')->with([
            'index' => 'sw_blog',
            'body' => [
                'properties' => [
                    'field1' => 'test1',
                    'field2' => 'test2',
                ],
            ],
        ]);

        $namespace->expects($this->once())->method('exists')->with(['index' => 'sw_blog'])->willReturn(true);

        $client->method('indices')->willReturn($namespace);

        $subscriber = new LanguageSubscriber(
            $esHelper,
            $registry,
            $client,
        );

        $event = $this->createMock(EntityWrittenEvent::class);
        $event
            ->expects($this->once())
            ->method('getResults')->willReturn(new EntityWriteResultCollection([$writeResult]));

        $subscriber->onLanguageWritten($event);
    }
}
