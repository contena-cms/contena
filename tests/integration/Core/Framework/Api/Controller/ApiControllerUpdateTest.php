<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerUpdateTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testResponseDataTypeOnWrite(): void
    {
        $id = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/category', ['id' => $id, 'name' => $id]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame('http://localhost/api/category/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->jsonRequest('PATCH', '/api/category/' . $id, ['name' => 'foo']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame('http://localhost/api/category/' . $id, $response->headers->get('Location'));

        $this->getBrowser()->jsonRequest('PATCH', '/api/category/' . $id . '?_response=1', ['name' => 'foo']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNull($response->headers->get('Location'));
    }

    public function testUpdateWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/category', ['id' => $id, 'name' => 'test category']);
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['category:read', 'category:create']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('PATCH', '/api/category/' . $id, ['name' => 'foo']);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());

        $browser->jsonRequest('GET', '/api/category/' . $id);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        $category = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('test category', $category['data']['attributes']['name']);
    }

    public function testAllowSettingNullToTranslatableFields(): void
    {
        $id = Uuid::randomHex();
        $client = $this->getBrowser();
        $client->jsonRequest('POST', '/api/category', [
            'id' => $id,
            'name' => 'test',
            'description' => 'test',
        ]);
        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $client->setServerParameter('HTTP_ct-language-id', Defaults::LANGUAGE_SYSTEM);
        $client->jsonRequest('PATCH', '/api/category/' . $id, ['description' => null]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testInvalidWriteInputExceptionIsConvertedToBadRequestOnUpdate(): void
    {
        $id = Uuid::randomHex();
        $client = $this->getBrowser();
        $client->jsonRequest('POST', '/api/category', ['id' => $id, 'name' => 'test']);
        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->jsonRequest('PATCH', '/api/category/' . $id, [2 => 'test']);
        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        static::assertSame(400, (int) $response['errors'][0]['status']);
        static::assertSame('Invalid payload. Should be associative array', (string) $response['errors'][0]['detail']);
    }
}
