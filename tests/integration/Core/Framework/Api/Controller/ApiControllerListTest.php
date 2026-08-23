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
class ApiControllerListTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSimpleFilter(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'List Test Country',
            'iso' => 'LT',
            'iso3' => 'LTC',
            'position' => 8300,
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/country', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        $query = http_build_query([
            'filter' => [
                'country.id' => $id,
                'country.position' => 8300,
                'country.name' => 'List Test Country',
            ],
        ]);

        $this->getBrowser()->request('GET', '/api/country?' . $query);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $content['meta']['total']);
        static::assertSame($id, $content['data'][0]['id']);
    }
}
