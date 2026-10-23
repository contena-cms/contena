<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\Review;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Util\FloatComparator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Group('store-api')]
class BlogReviewRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->setVisibilities();

        $this->createReviews();
    }

    public function testLoad(): void
    {
        $this->browser->request('POST', $this->getUrl());

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('total', $response);
        static::assertSame(5, $response['total']);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl(),
            [
                'includes' => [
                    'blog_review' => ['title', 'content', 'points'],
                ],
            ]
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        $first = array_shift($response['elements']);
        $properties = array_keys($first);

        $expected = ['title', 'content', 'points', 'apiAlias'];
        sort($properties);
        sort($expected);

        static::assertSame($expected, $properties);
    }

    public function testExtendCriteria(): void
    {
        $this->browser->request(
            'POST',
            $this->getUrl(),
            [
                'includes' => [
                    'blog_review' => ['title', 'content', 'points'],
                ],
                'aggregations' => [
                    ['name' => 'average', 'type' => 'avg', 'field' => 'points'],
                    ['name' => 'max', 'type' => 'max', 'field' => 'points'],
                ],
            ]
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('aggregations', $response);

        static::assertArrayHasKey('max', $response['aggregations']);
        static::assertArrayHasKey('average', $response['aggregations']);

        static::assertTrue(FloatComparator::equals(3.4, $response['aggregations']['average']['avg']));
        static::assertSame('5', $response['aggregations']['max']['max']);
    }

    private function createData(): void
    {
        $blog = [
            'id' => $this->ids->create('blog'),
            'manufacturer' => ['id' => $this->ids->create('manufacturer-'), 'name' => 'test-'],
            'blogNumber' => $this->ids->get('blog'),
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $update = [
            [
                'id' => $this->ids->get('blog'),
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ];
        static::getContainer()->get('blog.repository')
            ->update($update, Context::createDefaultContext());
    }

    private function createReviews(): void
    {
        $reviews = [];
        for ($i = 1; $i <= 5; ++$i) {
            $reviews[] = [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'channelId' => $this->ids->get('sales-channel'),
                'blogId' => $this->ids->get('blog'),
                'title' => 'Test',
                'content' => 'test',
                'externalUser' => 'Test',
                'points' => min(5, $i + $i / 5),
                'status' => true,
            ];
        }

        static::getContainer()->get('blog_review.repository')
            ->create($reviews, Context::createDefaultContext());
    }

    private function getUrl(): string
    {
        return '/store-api/blog/' . $this->ids->get('blog') . '/reviews';
    }

    private function getResponseContent(): string
    {
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        return $content;
    }
}
