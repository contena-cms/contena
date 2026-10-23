<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerSearchIdsTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSearchIdsOnManyToMany(): void
    {
        $id = Uuid::randomHex();
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        $data = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryA, 'name' => 'A'],
                ['id' => $categoryB, 'name' => 'B'],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/search-ids/blog-category';
        $this->getBrowser()->jsonRequest('POST', $path, [
            'filter' => [
                [
                    'type' => 'equalsAny',
                    'field' => 'blogId',
                    'value' => implode('|', [$id]),
                ],
            ],
        ]);
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertIsArray($responseData);
        static::assertArrayHasKey('total', $responseData);
        static::assertSame(2, $responseData['total']);
        static::assertArrayHasKey('data', $responseData);

        $categoryAFound = 0;
        $categoryBFound = 0;

        foreach ($responseData['data'] as $datum) {
            static::assertArrayHasKey('blogId', $datum);
            static::assertArrayHasKey('categoryId', $datum);
            static::assertSame($datum['blogId'], $id);

            if ($categoryA === $datum['categoryId']) {
                ++$categoryAFound;
            }

            if ($categoryB === $datum['categoryId']) {
                ++$categoryBFound;
            }
        }

        static::assertSame(1, $categoryAFound);
        static::assertSame(1, $categoryBFound);
    }
}
