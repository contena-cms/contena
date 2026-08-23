<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
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

        $country = [
            'id' => $id,
            'name' => 'Aggregate Test Country',
            'iso' => 'AT',
            'iso3' => 'ATC',
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/country', $country);

        $data = [
            'aggregations' => [
                [
                    'name' => 'total',
                    'field' => 'id',
                    'type' => 'count',
                ],
            ],
            'filter' => [
                ['type' => 'equals', 'field' => 'id', 'value' => $id],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/aggregate/country', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // data is empty as we only do aggregations
        static::assertEmpty($content['data']);
        static::assertArrayHasKey('aggregations', $content);
        static::assertSame(1, $content['aggregations']['total']['count']);
    }
}
