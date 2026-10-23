<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserIndexer;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class BlogDescriptionTeaserIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testHandleBackfillsMissingTeaser(): void
    {
        $ids = new IdsCollection();
        $this->createBlog($ids);

        $connection = static::getContainer()->get(Connection::class);

        // Simulate a row that existed before the column was added: the teaser is still missing.
        $connection->executeStatement(
            'UPDATE blog_translation SET description_teaser = NULL WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($ids->get('blog'))]
        );

        $this->getIndexer()->handle(new EntityIndexingMessage([$ids->get('blog')]));

        $teaser = $connection->fetchOne(
            'SELECT description_teaser FROM blog_translation WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($ids->get('blog'))]
        );

        static::assertSame('Hello World', $teaser);
    }

    public function testHandleRewritesStaleTeaser(): void
    {
        $ids = new IdsCollection();
        $this->createBlog($ids);

        $connection = static::getContainer()->get(Connection::class);

        // A non-null but stale teaser (e.g. left behind by a raw-SQL write or a change to the
        // html_sanitizer rules) is rebuilt from the current description.
        $connection->executeStatement(
            'UPDATE blog_translation SET description_teaser = :value WHERE blog_id = :id',
            ['value' => 'stale value', 'id' => Uuid::fromHexToBytes($ids->get('blog'))]
        );

        $this->getIndexer()->handle(new EntityIndexingMessage([$ids->get('blog')]));

        $teaser = $connection->fetchOne(
            'SELECT description_teaser FROM blog_translation WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($ids->get('blog'))]
        );

        static::assertSame('Hello World', $teaser);
    }

    public function testHandleLeavesUpToDateTeaserUntouched(): void
    {
        $ids = new IdsCollection();
        $this->createBlog($ids);

        $connection = static::getContainer()->get(Connection::class);

        // A teaser that already matches the current description is left as-is (no needless rewrite).
        $connection->executeStatement(
            'UPDATE blog_translation SET description_teaser = :value WHERE blog_id = :id',
            ['value' => 'Hello World', 'id' => Uuid::fromHexToBytes($ids->get('blog'))]
        );

        $this->getIndexer()->handle(new EntityIndexingMessage([$ids->get('blog')]));

        $teaser = $connection->fetchOne(
            'SELECT description_teaser FROM blog_translation WHERE blog_id = :id',
            ['id' => Uuid::fromHexToBytes($ids->get('blog'))]
        );

        static::assertSame('Hello World', $teaser);
    }

    public function testUpdateReturnsNullToLeaveLiveWritesToSubscriber(): void
    {
        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection(),
            []
        );

        static::assertNull($this->getIndexer()->update($event));
    }

    private function getIndexer(): BlogDescriptionTeaserIndexer
    {
        return static::getContainer()->get(BlogDescriptionTeaserIndexer::class);
    }

    private function createBlog(IdsCollection $ids): void
    {
        static::getContainer()->get('blog.repository')->create([[
            'id' => $ids->create('blog'),
            'blogNumber' => $ids->get('blog'),
            'name' => 'Teaser indexer probe',
            'description' => '<p style="color: red;">Hello <strong>World</strong></p>',
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
            'tax' => ['name' => 'probe', 'taxRate' => 19],
        ]], Context::createDefaultContext());
    }
}
