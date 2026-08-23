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
class ApiControllerVersionTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testCreateNewVersion(): void
    {
        $id = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/category', ['id' => $id, 'name' => 'test category']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));

        $this->getBrowser()->jsonRequest('POST', '/api/_action/version/category/' . $id);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        static::assertTrue(Uuid::isValid($content['versionId']));
        static::assertNull($content['versionName']);
        static::assertSame($id, $content['id']);
        static::assertSame('category', $content['entity']);
    }
}
