<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexingMessage;
use Contena\Core\Framework\Api\Controller\IndexingController;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class IndexingControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testIterateIndexerApiShouldReturnFinishTrueWithInvalidIndexer(): void
    {
        $this->getBrowser()->request('POST', '/api/_action/indexing/test.indexer', ['offset' => 0]);
        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($response['finish']);
    }

    #[DataProvider('provideOffsets')]
    public function testIterateIndexerApiShouldReturnCorrectOffset(int $offset): void
    {
        $blogIndexer = $this->createMock(BlogIndexer::class);
        if ($offset === 100) {
            $blogIndexer->method('iterate')->willReturn(null);
        } else {
            $blogIndexer->method('iterate')->willReturn(new BlogIndexingMessage(
                [Uuid::randomHex()],
                ['offset' => $offset + 50]
            ));
        }

        $registry = static::createStub(EntityIndexerRegistry::class);
        $registry->method('getIndexer')->willReturn($blogIndexer);
        $indexer = new IndexingController($registry);

        $response = $indexer->iterate('blog.indexer', new Request([], ['offset' => $offset]), Context::createDefaultContext());
        $content = $response->getContent();
        static::assertIsString($content);
        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        if ($offset === 100) {
            static::assertTrue($response['finish']);
        } else {
            static::assertFalse($response['finish']);
            static::assertSame(['offset' => $offset + 50], $response['offset']);
        }
    }

    /**
     * @return array<string, array<int>>
     */
    public static function provideOffsets(): array
    {
        return [
            'offset 0' => [0],
            'offset 50' => [50],
            'offset 100' => [100],
        ];
    }
}
