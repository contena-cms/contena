<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class BlogListRouteTest extends TestCase
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
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities();
    }

    public function testListingBlogs(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/blog',
            [
            ]
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(15, $response['total']);
        static::assertCount(15, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testFetchingTranslations(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/blog',
            [
                'ids' => [$this->ids->get('blog1')],
                'associations' => ['translations' => []],
            ]
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('elements', $response);
        static::assertCount(1, $response['elements']);
        static::assertArrayHasKey('translations', $response['elements'][0]);
        static::assertCount(2, $response['elements'][0]['translations']);

        $languages = \array_column($response['elements'][0]['translations'], 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languages);
        static::assertContains($this->ids->get('language'), $languages);

        $names = \array_column($response['elements'][0]['translations'], 'name');
        static::assertContains('Test-Blog', $names);
        static::assertContains('Other translation', $names);
    }

    public function testListingBlogsLimit(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/blog?limit=1',
            [
            ]
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testListingBlogsIds(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/blog',
            [
                'ids' => [
                    $this->ids->get('blog1'),
                ],
            ]
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testListingBlogsIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/blog',
            [
                'includes' => [
                    'blog' => ['id'],
                ],
            ]
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(15, $response['total']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('apiAlias', $response['elements'][0]);
        static::assertArrayNotHasKey('name', $response['elements'][0]);
    }

    public function testListingBlogsIncludesOnlyPublicReviews(): void
    {
        $blog = (new BlogBuilder($this->ids, 'p1'))
            ->visibility($this->ids->get('sales-channel'))
            ->price(10)
            ->review('test public review', 'this is a public review', 3, $this->ids->get('sales-channel'))
            ->review('test hidden review', 'this is a hidden review', 1, $this->ids->get('sales-channel'), Defaults::LANGUAGE_SYSTEM, false)
            ->build();

        static::getContainer()->get('blog.repository')
            ->upsert([$blog], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/blog',
            [
                'filter' => [[
                    'type' => 'equals',
                    'field' => 'blogNumber',
                    'value' => 'p1',
                ]],
                'associations' => [
                    'blogReviews' => [],
                ],
            ],
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('blogReviews', $response['elements'][0]);
        $reviews = $response['elements'][0]['blogReviews'];
        static::assertCount(1, $reviews);
        static::assertSame('test public review', $reviews[0]['title']);
    }

    public function testListingBlogsIncludesOwnInactiveReviews(): void
    {
        $userId = $this->login($this->browser);

        $blog = (new BlogBuilder($this->ids, 'p1'))
            ->visibility($this->ids->get('sales-channel'))
            ->price(10)
            ->review('test public review', 'this is a public review', 3, $this->ids->get('sales-channel'))
            ->review('test hidden own review', 'this is a hidden review', 1, $this->ids->get('sales-channel'), Defaults::LANGUAGE_SYSTEM, false, $userId)
            ->build();

        static::getContainer()->get('blog.repository')
            ->upsert([$blog], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/blog',
            [
                'filter' => [[
                    'type' => 'equals',
                    'field' => 'blogNumber',
                    'value' => 'p1',
                ]],
                'associations' => [
                    'blogReviews' => [
                        'sort' => [['field' => 'points', 'order' => FieldSorting::DESCENDING]],
                    ],
                ],
            ],
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('blogReviews', $response['elements'][0]);
        $reviews = $response['elements'][0]['blogReviews'];
        static::assertCount(2, $reviews);
        static::assertSame('test public review', $reviews[0]['title']);
        static::assertSame('test hidden own review', $reviews[1]['title']);
    }

    #[DataProvider('nestedReviewOwnerProvider')]
    public function testListingBlogsAppliesReviewVisibilityToNestedAssociations(bool $pendingReviewBelongsToTheCaller, bool $pendingReviewIsVisible): void
    {
        $otherUserId = $this->login($this->browser);
        // the second login switches the browser to another user, so the pending review below
        // either belongs to the caller or to the user created first
        $callerId = $this->login($this->browser);

        $reviewOwnerId = $pendingReviewBelongsToTheCaller ? $callerId : $otherUserId;

        $blog = (new BlogBuilder($this->ids, 'nested-review-parent'))
            ->visibility($this->ids->get('sales-channel'))
            ->price(10)
            ->variant(
                (new BlogBuilder($this->ids, 'nested-review-child'))
                    ->visibility($this->ids->get('sales-channel'))
                    ->price(10)
                    ->review(
                        title: 'test approved review',
                        content: 'this is an approved review',
                        points: 3,
                        channelId: $this->ids->get('sales-channel'),
                    )
                    ->review(
                        title: 'test pending review',
                        content: 'this is a pending review',
                        points: 1,
                        channelId: $this->ids->get('sales-channel'),
                        status: false,
                        userId: $reviewOwnerId,
                    )
                    ->build()
            )
            ->build();

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/blog',
            [
                'ids' => [$this->ids->get('nested-review-parent')],
                'associations' => [
                    'children' => [
                        'associations' => [
                            'blogReviews' => [
                                'sort' => [['field' => 'points', 'order' => FieldSorting::DESCENDING]],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertArrayHasKey('children', $response['elements'][0]);
        static::assertCount(1, $response['elements'][0]['children']);
        static::assertArrayHasKey('blogReviews', $response['elements'][0]['children'][0]);

        $titles = \array_column($response['elements'][0]['children'][0]['blogReviews'], 'title');

        $expected = $pendingReviewIsVisible
            ? ['test approved review', 'test pending review']
            : ['test approved review'];

        static::assertSame($expected, $titles);
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function nestedReviewOwnerProvider(): iterable
    {
        yield 'pending review of the calling user is visible' => [true, true];
        yield 'pending review of another user is hidden' => [false, false];
    }

    public function testListingBlogsLimitsChildrenAssociation(): void
    {
        $blog = (new BlogBuilder($this->ids, 'parent-with-limited-children'))
            ->price(10)
            ->visibility($this->ids->get('sales-channel'))
            ->variant(
                (new BlogBuilder($this->ids, 'limited-child-1'))
                    ->price(10)
                    ->visibility($this->ids->get('sales-channel'))
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'limited-child-2'))
                    ->price(10)
                    ->visibility($this->ids->get('sales-channel'))
                    ->build()
            )
            ->variant(
                (new BlogBuilder($this->ids, 'limited-child-3'))
                    ->price(10)
                    ->visibility($this->ids->get('sales-channel'))
                    ->build()
            )
            ->build();

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/blog',
            [
                'ids' => [$this->ids->get('parent-with-limited-children')],
                'associations' => [
                    'children' => [
                        'limit' => 2,
                    ],
                ],
            ],
        );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
        static::assertArrayHasKey('children', $response['elements'][0]);
        static::assertCount(2, $response['elements'][0]['children']);
    }

    private function createData(): void
    {
        $blogs = [];

        static::getContainer()->get('language.repository')->create(
            [
                [
                    'id' => $this->ids->create('language'),
                    'name' => 'foo',
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'active' => true,
                    'translationCode' => [
                        'code' => 'de-DE-' . Uuid::randomHex(),
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'channels' => [
                        ['id' => TestDefaults::CHANNEL],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        for ($i = 0; $i < 15; ++$i) {
            $blogs[] = (new BlogBuilder($this->ids, 'blog' . $i))
                ->name('Test-Blog')
                ->stock(10)
                ->price(15)
                ->translation($this->ids->create('language'), 'name', 'Other translation')
                ->manufacturer('manufacturer-' . $i)
                ->build();
        }

        $data = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'blog_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'blog-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'blog-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'blogs' => $blogs,
        ];

        static::getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $blogs = [];
        for ($i = 0; $i < 15; ++$i) {
            $blogs[] = [
                'id' => $this->ids->get('blog' . $i),
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        static::getContainer()->get('blog.repository')
            ->update($blogs, Context::createDefaultContext());
    }

    private function getResponseContent(): string
    {
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        return $content;
    }
}
