<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\Admin\AdminElasticsearchHelper;
use Contena\Elasticsearch\Admin\AdminSearchController;
use Contena\Elasticsearch\Admin\AdminSearcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AdminSearchController::class)]
class AdminSearchControllerTest extends TestCase
{
    private AdminSearcher $searcher;

    protected function setUp(): void
    {
        $this->searcher = static::createStub(AdminSearcher::class);

        $blog = new BlogEntity();
        $blog->setUniqueIdentifier(Uuid::randomHex());
        $this->searcher->method('search')->willReturn([
            'blog' => [
                'total' => 1,
                'data' => new EntityCollection([$blog]),
                'indexer' => 'blog-listing',
                'index' => 'ct-admin-blog-listing',
            ],
        ]);
    }

    public function testElasticSearchWithElasticSearchNotEnable(): void
    {
        $controller = new AdminSearchController(
            static::createStub(AdminSearcher::class),
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(JsonEntityEncoder::class),
            new AdminElasticsearchHelper(false, false, 'ct-admin', 'test', true, new NullLogger())
        );

        $request = new Request();
        $request->request->set('term', 'test');

        $this->expectExceptionObject(new \RuntimeException('Admin elasticsearch is not enabled'));

        $controller->elastic($request, Context::createDefaultContext());
    }

    public function testElasticSearchWithEmptySearchTerm(): void
    {
        $controller = new AdminSearchController(
            static::createStub(AdminSearcher::class),
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(JsonEntityEncoder::class),
            new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger())
        );

        $request = new Request();
        $request->request->set('term', '   ');

        $this->expectExceptionObject(new \RuntimeException('Parameter "term" is missing.'));

        $controller->elastic($request, Context::createDefaultContext());
    }

    public function testElasticSearch(): void
    {
        $controller = new AdminSearchController(
            $this->searcher,
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(JsonEntityEncoder::class),
            new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger())
        );

        $request = new Request();
        $request->request->set('term', 'test');
        $response = $controller->elastic($request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $content = $response->getContent();
        static::assertIsString($content);
        $content = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        $data = $content['data'];

        static::assertNotEmpty($data['blog']);

        static::assertSame(1, $data['blog']['total']);
        static::assertNotEmpty($data['blog']['data']);
        static::assertSame('blog-listing', $data['blog']['indexer']);
        static::assertSame('ct-admin-blog-listing', $data['blog']['index']);
    }
}
