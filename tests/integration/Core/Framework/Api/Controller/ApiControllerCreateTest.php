<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerCreateTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testInsert(): void
    {
        $id = Uuid::randomHex();
        $data = ['id' => $id, 'name' => $id, 'type' => BlogDefinition::TYPE_POST, 'active' => true];

        $this->getBrowser()->jsonRequest('POST', '/api/blog', $data);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        static::assertSame('http://localhost/api/blog/' . $id, $response->headers->get('Location'));
        $this->assertEntityExists($this->getBrowser(), 'blog', $id);
    }

    public function testInsertAuthenticatedWithIntegration(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowserAuthenticatedWithIntegration();
        $browser->jsonRequest('POST', '/api/blog', ['id' => $id, 'name' => $id]);

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame('http://localhost/api/blog/' . $id, $browser->getResponse()->headers->get('Location'));
        $this->assertEntityExists($browser, 'blog', $id);
    }

    public function testManyToManyInsert(): void
    {
        $id = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/blog', [
            'id' => $id,
            'name' => 'Blog with category',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->jsonRequest('POST', '/api/category', ['id' => $categoryId, 'name' => 'Category - 1']);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->jsonRequest('POST', '/api/blog-category', [
            'blogId' => $id,
            'categoryId' => $categoryId,
            'blogVersionId' => Defaults::LIVE_VERSION,
            'categoryVersionId' => Defaults::LIVE_VERSION,
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $mapping = static::getContainer()->get(Connection::class)->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :blogId AND category_id = :categoryId',
            ['blogId' => Uuid::fromHexToBytes($id), 'categoryId' => Uuid::fromHexToBytes($categoryId)]
        );
        static::assertCount(1, $mapping);
    }

    public function testManyToManyInsertWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/blog', ['id' => $id, 'name' => 'Blog']);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());
        $browser->jsonRequest('POST', '/api/category', ['id' => $categoryId, 'name' => 'Category']);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['blog:read', 'category:read']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/blog-category', [
            'blogId' => $id,
            'categoryId' => $categoryId,
            'blogVersionId' => Defaults::LIVE_VERSION,
            'categoryVersionId' => Defaults::LIVE_VERSION,
        ]);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
    }

    public function testInvalidWriteInputExceptionIsConvertedToBadRequestOnCreate(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/blog', [2 => 'test']);
        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, (int) $response['errors'][0]['status']);
        static::assertSame('Invalid payload. Should be associative array', $response['errors'][0]['detail']);
    }
}
