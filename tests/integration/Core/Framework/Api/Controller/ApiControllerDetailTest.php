<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerDetailTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testJsonApiResponseSingle(): void
    {
        $id = Uuid::randomHex();
        $insertData = ['id' => $id, 'name' => 'test'];

        $this->getBrowser()->jsonRequest('POST', '/api/category', $insertData);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $location = $response->headers->get('Location');
        static::assertNotEmpty($location);

        static::assertIsString($location);
        $this->getBrowser()->jsonRequest('GET', $location);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($responseData);
        static::assertArrayHasKey('data', $responseData);
        static::assertArrayHasKey('links', $responseData);
        static::assertArrayHasKey('included', $responseData);

        $category = $responseData['data'];
        static::assertArrayHasKey('type', $category);
        static::assertArrayHasKey('id', $category);
        static::assertArrayHasKey('attributes', $category);
        static::assertArrayHasKey('links', $category);
        static::assertArrayHasKey('relationships', $category);
        static::assertArrayHasKey('translations', $category['relationships']);
        static::assertArrayHasKey('meta', $category);
        static::assertArrayHasKey('translated', $category['attributes']);
        static::assertArrayHasKey('name', $category['attributes']['translated']);

        static::assertSame($id, $category['id']);
        static::assertSame('category', $category['type']);
        static::assertSame($insertData['name'], $category['attributes']['name']);
        static::assertSame($insertData['name'], $category['attributes']['translated']['name']);
    }
}
