<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerCloneTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCloneEntity(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tag clone',
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/tag', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->jsonRequest('GET', '/api/tag/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $tag = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $tag);
        static::assertSame($id, $tag['data']['id']);

        $this->getBrowser()->jsonRequest('POST', '/api/_action/clone/tag/' . $id, $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('id', $data);
        static::assertNotSame($id, $data['id']);

        $newId = $data['id'];
        $this->getBrowser()->jsonRequest('GET', '/api/tag/' . $newId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('test tag clone', $data['data']['attributes']['name']);
    }

    public function testCloneEntityWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tag clone',
        ];

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/tag', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $connection = $browser->getContainer()->get(Connection::class);
        TestUser::createNewTestUser(
            $connection,
            ['tag:read']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('GET', '/api/tag/' . $id);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $tag = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $tag);
        static::assertSame($id, $tag['data']['id']);

        $browser->jsonRequest('POST', '/api/_action/clone/tag/' . $id, $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }
}
