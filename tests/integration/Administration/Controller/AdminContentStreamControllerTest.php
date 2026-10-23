<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Administration\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
class AdminBlogStreamControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->prepareTestData();
    }

    public function testPreview(): void
    {
        $data = [
            'page' => 1,
            'limit' => 25,
            'filter' => [
                [
                    'field' => null,
                    'type' => 'multi',
                    'operator' => 'OR',
                    'value' => null,
                    'parameters' => null,
                    'queries' => [
                        [
                            'field' => null,
                            'type' => 'multi',
                            'operator' => 'AND',
                            'value' => null,
                            'parameters' => null,
                            'queries' => [
                                [
                                    'field' => 'cheapestPrice',
                                    'type' => 'range',
                                    'operator' => null,
                                    'value' => null,
                                    'parameters' => [
                                        'lt' => '500',
                                    ],
                                    'queries' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_admin/blog-stream-preview/' . TestDefaults::CHANNEL,
            $data
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(3, $content['elements']);
        $names = array_column($content['elements'], 'name');
        static::assertContains('v.1.1', $names);
        static::assertNotContains('v.1.2', $names);
        static::assertContains('v.2.1', $names);
        static::assertContains('v.2.2', $names);
    }

    public function testEqualsAllPreview(): void
    {
        $this->ids->create('rot');
        $this->ids->create('grün');

        $data = [
            'page' => 1,
            'limit' => 25,
            'filter' => [
                [
                    'field' => null,
                    'type' => 'multi',
                    'operator' => 'OR',
                    'value' => null,
                    'parameters' => null,
                    'queries' => [
                        [
                            'field' => null,
                            'type' => 'multi',
                            'operator' => 'AND',
                            'value' => null,
                            'parameters' => null,
                            'queries' => [
                                [
                                    'field' => 'properties.id',
                                    'type' => 'equalsAll',
                                    'operator' => null,
                                    'value' => $this->ids->get('grün') . '|' . $this->ids->get('rot'),
                                    'parameters' => null,
                                    'queries' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_admin/blog-stream-preview/' . TestDefaults::CHANNEL,
            $data
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(3, $content['elements']);
        $names = array_column($content['elements'], 'name');
        static::assertContains('v.1.1', $names);
        static::assertContains('v.1.2', $names);
        static::assertNotContains('v.2.1', $names);
        static::assertNotContains('v.2.2', $names);
    }

    private function prepareTestData(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');

        $blogs = [
        ];

        static::getContainer()->get('blog.repository')
            ->create($blogs, Context::createDefaultContext());

        static::getContainer()->get('rule.repository')->upsert(
            [
                ['id' => $this->ids->get('rule-a'), 'priority' => 1],
                ['id' => $this->ids->get('rule-b'), 'priority' => 2],
                ['id' => $this->ids->get('rule-c'), 'priority' => 3],
                ['id' => $this->ids->get('rule-d'), 'priority' => 4],
                ['id' => $this->ids->get('rule-e'), 'priority' => 5],
            ],
            Context::createDefaultContext()
        );
    }
}
