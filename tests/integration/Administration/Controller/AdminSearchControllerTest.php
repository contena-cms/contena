<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\OAuth\Scope\AdminUserVerifiedScope;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class AdminSearchControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function setUp(): void
    {
        $roles = ['blog:read', 'blog_manufacturer:read', 'admin_user:read'];

        $this->authorizeBrowser($this->getBrowser(), [AdminUserVerifiedScope::IDENTIFIER], $roles);

        $this->prepareTestData();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, int> $expectedEntities
     * @param array<string, string> $expectedErrors
     */
    #[DataProvider('searchDataProvider')]
    public function testSearch(array $data, bool $hasResponse, array $expectedEntities, array $expectedErrors = []): void
    {
        $this->getBrowser()->request('POST', '/api/_admin/search', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR) ?: null);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content, print_r($content, true));

        if (!$hasResponse) {
            static::assertSame([], $content['data']);

            return;
        }

        static::assertIsArray($content['data']);
        static::assertNotCount(0, $content['data']);

        $data = $content['data'];

        foreach ($expectedErrors as $entity => $expectedErrorDetail) {
            static::assertArrayHasKey($entity, $data);

            $actual = $data[$entity];

            unset($data[$entity]);

            static::assertSame($expectedErrorDetail, $actual['detail']);
        }

        static::assertCount(\count($expectedEntities), $data);

        foreach ($expectedEntities as $entity => $expectedTotal) {
            static::assertArrayHasKey($entity, $data);

            $actual = $data[$entity];

            static::assertSame($expectedTotal, $actual['total']);
        }
    }

    public function testSearchResultWithoutApiAwareField(): void
    {
        $this->getBrowser()->request('POST', '/api/_admin/search', [], [], [], json_encode([
            'admin_user' => [
                'query' => [],
            ],
        ]) ?: null);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content, print_r($content, true));

        static::assertIsArray($content['data']['admin_user']['data']);
        static::assertNotCount(0, $content['data']['admin_user']['data']);

        $adminUser = array_values($content['data']['admin_user']['data'])[0];

        static::assertSame('admin_user', $adminUser['apiAlias']);
        static::assertArrayNotHasKey('password', $adminUser);
    }

    /**
     * @return iterable<array{0: array<string, mixed>, 1: bool, 2: array<string, int>, 3?: array<string, string>}>
     */
    public static function searchDataProvider(): iterable
    {
        yield 'basic test with query' => [
            [
                'blog' => [
                    'query' => [
                        [
                            'score' => 2500,
                            'query' => [
                                'type' => 'contains',
                                'field' => 'name',
                                'value' => 'Fancy Blog ABC',
                            ],
                        ],
                    ],
                ],
            ], true, ['blog' => 1],
        ];
        yield 'basic test with term' => [
            [
                'blog' => [
                    'term' => 'ABC123',
                ],
            ], true, ['blog' => 1],
        ];
        yield 'empty payload' => [
            [
            ], false, [],
        ];
        yield 'empty result' => [
            [
                'blog' => [
                    'term' => 'TheTermThatNotHappens',
                ],
            ], true, ['blog' => 0],
        ];
        yield 'not found entity payload' => [
            [
                'not-found-entity' => [
                    'term' => 'ABC',
                ],
            ], false, [],
        ];
        yield 'duplicate entities in payload (the later one will be used)' => [
            json_decode('{"blog":{"term":"ABC"},"blog":{"term":"XYZ"}}', true), true, ['blog' => 0],
        ];
        yield 'multiple entities in payload' => [
            [
                'blog' => [
                    'query' => [
                        [
                            'score' => 2500,
                            'query' => [
                                'type' => 'contains',
                                'field' => 'name',
                                'value' => 'Fancy',
                            ],
                        ],
                    ],
                ],
                'blog_manufacturer' => [
                    'term' => 'Cheapest ever 2nd',
                ],
            ], true, ['blog' => 2, 'blog_manufacturer' => 1],
        ];
        yield 'non-granted privilege entities in payload' => [
            [
                'blog' => [
                    'query' => [
                        [
                            'score' => 2500,
                            'query' => [
                                'type' => 'contains',
                                'field' => 'name',
                                'value' => 'Fancy',
                            ],
                        ],
                    ],
                ],
                'user' => [
                    'term' => 'John doe',
                ],
            ], true, ['blog' => 2], [
                'user' => '{"message":"Missing privilege","missingPrivileges":["user:read"]}',
            ],
        ];
        yield 'Only non-granted privilege entities in payload' => [
            [
                'category' => [
                    'query' => [
                        [
                            'score' => 2500,
                            'query' => [
                                'type' => 'contains',
                                'field' => 'name',
                                'value' => 'Fancy',
                            ],
                        ],
                    ],
                ],
                'user' => [
                    'term' => 'John doe',
                ],
            ], true, [], [
                'category' => '{"message":"Missing privilege","missingPrivileges":["category:read"]}',
                'user' => '{"message":"Missing privilege","missingPrivileges":["user:read"]}',
            ],
        ];
        yield 'search with association field' => [
            [
                'blog' => [
                    'query' => [
                        [
                            'score' => 2500,
                            'query' => [
                                'type' => 'contains',
                                'field' => 'manufacturer.name',
                                'value' => 'Manufacturer',
                            ],
                        ],
                    ],
                ],
            ], true, ['blog' => 4],
        ];
        yield 'search with non-granted priviledge association field' => [
            [
                'blog' => [
                    'query' => [
                        [
                            'score' => 2500,
                            'query' => [
                                'type' => 'contains',
                                'field' => 'categories.name',
                                'value' => 'test',
                            ],
                        ],
                    ],
                ],
                'blog_manufacturer' => [
                    'term' => 'Cheapest ever 2nd',
                ],
            ], true, ['blog_manufacturer' => 1], ['blog' => '{"message":"Missing privilege","missingPrivileges":["category:read"]}'],
        ];
        yield 'load association with granted privilege entities' => [
            [
                'blog' => [
                    'query' => [
                        [
                            'score' => 2500,
                            'query' => [
                                'type' => 'contains',
                                'field' => 'manufacturer.name',
                                'value' => 'Manufacturer',
                            ],
                        ],
                    ],
                    'associations' => [
                        'manufacturer' => ['total-count-mode' => 1],
                    ],
                ],
            ], true, ['blog' => 4],
        ];
        yield 'load association with non-granted privilege entities' => [
            [
                'blog' => [
                    'query' => [
                        [
                            'score' => 2500,
                            'query' => [
                                'type' => 'contains',
                                'field' => 'manufacturer.name',
                                'value' => 'Manufacturer',
                            ],
                        ],
                    ],
                    'associations' => [
                        'categories' => ['total-count-mode' => 1],
                    ],
                ],
            ], true, [], ['blog' => '{"message":"Missing privilege","missingPrivileges":["category:read"]}'],
        ];
    }

    private function prepareTestData(): void
    {
        $fancyManufacturer = Uuid::randomHex();
        $cheapestManufacturer = Uuid::randomHex();

        $blogRepository = static::getContainer()->get('blog.repository');
        $manufacturerRepository = static::getContainer()->get('blog_manufacturer.repository');

        $manufacturerRepository->upsert([
            ['id' => $fancyManufacturer, 'name' => 'Fancy Manufacturer'],
            ['id' => $cheapestManufacturer, 'name' => 'Cheapest Manufacturer'],
        ], Context::createDefaultContext());

        $blogRepository->upsert([
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 1, 'name' => 'Fancy Blog ABC123', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['id' => $fancyManufacturer]],
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 1, 'name' => 'Fancy Blog DEF456', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['id' => $fancyManufacturer]],
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 2, 'name' => 'Cheapest ever 1st', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['id' => $cheapestManufacturer]],
            ['id' => Uuid::randomHex(), 'blogNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 2, 'name' => 'Cheapest ever 2nd', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['id' => $cheapestManufacturer]],
        ], Context::createDefaultContext());
    }
}
