<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ApiControllerAggregateTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAggregate(): void
    {
        $id = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'blogNumber' => 'SW-API-14999',
            'stock' => 1,
            'name' => 'asdf',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Contena'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/blog', $blog);

        $data = [
            'aggregations' => [
                [
                    'name' => 'total',
                    'field' => 'id',
                    'type' => 'count',
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/aggregate/blog', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // data is empty as we only do aggregations
        static::assertSame([], $content['data']);
        static::assertArrayHasKey('aggregations', $content);
        static::assertSame(1, $content['aggregations']['total']['count']);
    }
}
