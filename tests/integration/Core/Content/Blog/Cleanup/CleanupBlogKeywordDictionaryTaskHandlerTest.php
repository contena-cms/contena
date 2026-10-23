<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Cleanup\CleanupBlogKeywordDictionaryTaskHandler;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class CleanupBlogKeywordDictionaryTaskHandlerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CleanupBlogKeywordDictionaryTaskHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = static::getContainer()->get(CleanupBlogKeywordDictionaryTaskHandler::class);
    }

    public function testCleanup(): void
    {
        $now = new \DateTimeImmutable();
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $blog = (new BlogBuilder($ids, 'test'))->price(100)->build();

        static::getContainer()->get('blog.repository')->create([$blog], $context);

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog_keyword_dictionary');

        $blogId = Uuid::fromHexToBytes($blog['id']);
        $this->createBlogSearchKeyword('test 1', $blogId, $now, $context);
        $this->createBlogSearchKeyword('test 2', $blogId, $now, $context);

        $this->createBlogKeywordDictionary('test 1');
        $this->createBlogKeywordDictionary('test 2');
        $this->createBlogKeywordDictionary('test 3');
        $this->createBlogKeywordDictionary('test 4');

        $this->handler->run();

        $keywordDictionaries = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT keyword FROM blog_keyword_dictionary');

        static::assertCount(2, $keywordDictionaries);
        static::assertContains('test 1', $keywordDictionaries);
        static::assertContains('test 2', $keywordDictionaries);
    }

    private function createBlogSearchKeyword(string $keyword, string $blogId, \DateTimeImmutable $date, Context $context): void
    {
        $searchKeyword = [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::randomBytes(),
            'blog_version_id' => Uuid::fromHexToBytes($context->getVersionId()),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'blog_id' => $blogId,
            'keyword' => $keyword,
            'ranking' => 100,
            'created_at' => $date->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        static::getContainer()->get(Connection::class)->insert('blog_search_keyword', $searchKeyword);
    }

    private function createBlogKeywordDictionary(string $keyword): void
    {
        $searchKeyword = [
            'id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'keyword' => $keyword,
        ];

        static::getContainer()->get(Connection::class)->insert('blog_keyword_dictionary', $searchKeyword);
    }
}
