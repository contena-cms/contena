<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerSearchTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSearchTerm(): void
    {
        $id = Uuid::randomHex();
        $this->getBrowser()->jsonRequest('POST', '/api/blog', ['id' => $id, 'name' => 'Searchable Blog']);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->jsonRequest('POST', '/api/search/blog', [
            'page' => 1,
            'limit' => 5,
            'sort' => [['field' => 'name', 'order' => 'desc']],
            'term' => 'Searchable Blog',
        ]);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(1, $content['meta']['total']);
        static::assertSame($id, $content['data'][0]['id']);
    }

    public function testSearch(): void
    {
        $id = Uuid::randomHex();
        $this->getBrowser()->jsonRequest('POST', '/api/blog', ['id' => $id, 'name' => 'Content Search']);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->jsonRequest('POST', '/api/search/blog', [
            'page' => 1,
            'limit' => 5,
            'total-count-mode' => Criteria::TOTAL_COUNT_MODE_EXACT,
            'sort' => [['field' => 'blog.name', 'order' => 'desc']],
            'filter' => [['type' => 'equals', 'field' => 'blog.id', 'value' => $id]],
            'query' => [['type' => 'score', 'query' => ['type' => 'contains', 'field' => 'blog.name', 'value' => 'Content']]],
        ]);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(1, $content['meta']['total']);
        static::assertSame($id, $content['data'][0]['id']);
    }

    public function testSearchWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/blog', ['id' => $id, 'name' => 'Restricted Blog']);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['blog:create']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/search/blog', ['page' => 1, 'limit' => 5]);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
    }

    public function testAggregation(): void
    {
        $id = Uuid::randomHex();
        $this->getBrowser()->jsonRequest('POST', '/api/blog', ['id' => $id, 'name' => 'Aggregation Blog']);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->jsonRequest('POST', '/api/search/blog', [
            'aggregations' => [['name' => 'blog_count', 'type' => 'count', 'field' => 'blog.id']],
            'filter' => [['type' => 'equals', 'field' => 'blog.id', 'value' => $id]],
        ]);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(1, $content['aggregations']['blog_count']['count']);
    }
}
