<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Infrastructure\Path\MediaPathPostUpdater;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\RegisteredIndexerSubscriber;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Contena\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;

/**
 * @internal
 */
#[CoversClass(RegisteredIndexerSubscriber::class)]
class RegisteredIndexerSubscriberTest extends TestCase
{
    public function testSendsMessage(): void
    {
        $blogIndexer = static::createStub(BlogIndexer::class);
        $blogIndexer->method('getOptions')->willReturn(['seo', 'search', 'other-stuff']);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn(['blog.indexer' => ['seo']]);
        $queuer->expects($this->once())->method('finishIndexer')->with(['blog.indexer']);

        $indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistry->expects($this->once())->method('getIndexer')->with('blog.indexer')->willReturn($blogIndexer);
        $indexerRegistry->expects($this->once())->method('sendIndexingMessage')->with(['blog.indexer'], ['search', 'other-stuff'], true);
        $indexerRegistry->expects($this->never())->method('index');

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistry
        );
        $subscriber->runRegisteredIndexers();
    }

    public function testSendsMessageWithoutOptions(): void
    {
        $blogIndexer = static::createStub(BlogIndexer::class);
        $blogIndexer->method('getOptions')->willReturn(['seo', 'search', 'other-stuff']);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn(['blog.indexer' => []]);
        $queuer->expects($this->once())->method('finishIndexer')->with(['blog.indexer']);

        $indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistry->expects($this->once())->method('getIndexer')->with('blog.indexer')->willReturn($blogIndexer);
        $indexerRegistry->expects($this->once())->method('sendIndexingMessage')->with(['blog.indexer'], [], true);
        $indexerRegistry->expects($this->never())->method('index');

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistry
        );
        $subscriber->runRegisteredIndexers();
    }

    public function testSendsMessageToSynchronousPostUpdaterIndexer(): void
    {
        $pathPostUpdater = static::createStub(MediaPathPostUpdater::class);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn(['media.path.post_update' => []]);
        $queuer->expects($this->once())->method('finishIndexer')->with(['media.path.post_update']);

        $indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistry->expects($this->once())->method('getIndexer')->with('media.path.post_update')->willReturn($pathPostUpdater);
        $indexerRegistry->expects($this->never())->method('sendIndexingMessage');
        $indexerRegistry->expects($this->once())->method('index')->with(false, [], ['media.path.post_update'], true);

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistry
        );
        $subscriber->runRegisteredIndexers();
    }

    public function testEmptyQueue(): void
    {
        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn([]);
        $queuer->expects($this->never())->method('finishIndexer');

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            static::createStub(EntityIndexerRegistry::class)
        );

        $subscriber->runRegisteredIndexers();
    }

    public function testIgnoresUnknownIndexer(): void
    {
        $blogIndexer = static::createStub(BlogIndexer::class);
        $blogIndexer->method('getOptions')->willReturn(['seo', 'search', 'other-stuff']);

        $queuer = $this->createMock(IndexerQueuer::class);
        $queuer->expects($this->once())->method('getIndexers')->willReturn(['blog.indexer' => ['seo'], 'unknown.indexer' => []]);
        $queuer->expects($this->once())->method('finishIndexer')->with(['blog.indexer', 'unknown.indexer']);

        $indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $indexerRegistry
            ->expects($this->exactly(2))
            ->method('getIndexer')
            ->willReturnCallback(static fn (string $name) => $name === 'blog.indexer' ? $blogIndexer : null);

        $indexerRegistry->expects($this->once())->method('sendIndexingMessage')->with(['blog.indexer'], ['search', 'other-stuff']);

        $subscriber = new RegisteredIndexerSubscriber(
            $queuer,
            $indexerRegistry
        );

        $subscriber->runRegisteredIndexers();
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                UpdatePostFinishEvent::class => 'runRegisteredIndexers',
                FirstRunWizardFinishedEvent::class => 'runRegisteredIndexers',
                PluginPostInstallEvent::class => 'runRegisteredIndexers',
                PluginPostUpdateEvent::class => 'runRegisteredIndexers',
                PluginPostUninstallEvent::class => 'runRegisteredIndexers',
            ],
            RegisteredIndexerSubscriber::getSubscribedEvents()
        );
    }
}
