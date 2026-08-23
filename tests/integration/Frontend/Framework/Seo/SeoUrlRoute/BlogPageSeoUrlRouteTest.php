<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class BlogPageSeoUrlRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private const string DEFAULT_WEB_CHANNEL_ID = 'c6d2905ae914eb8d6320c54d2d1cab04';

    public function testMainCategories(): void
    {
        $ids = new IdsCollection();

        $channel = $this->createChannel();

        $blog = new BlogBuilder($ids, 'b1')
            ->visibility(self::DEFAULT_WEB_CHANNEL_ID)
            ->visibility($channel['id'])
            ->categories(['c1', 'c2'])
            ->mainCategory(self::DEFAULT_WEB_CHANNEL_ID, 'c1')
            ->mainCategory($channel['id'], 'c2')
            ->build();

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->generateAndAssert(
            ids: array_values($ids->getList(['b1'])),
            template: '{{ blog.mainCategories.first.category.translated.name }}',
            channelId: self::DEFAULT_WEB_CHANNEL_ID,
            expected: ['c1']
        );

        $this->generateAndAssert(
            ids: array_values($ids->getList(['b1'])),
            template: '{{ blog.mainCategories.first.category.translated.name }}',
            channelId: $channel['id'],
            expected: ['c2']
        );
    }

    /**
     * @param list<string> $ids
     * @param list<string> $expected
     */
    private function generateAndAssert(array $ids, string $template, string $channelId, array $expected): void
    {
        $context = Context::createDefaultContext();

        $channels = static::getContainer()
            ->get('channel.repository')
            ->search(new Criteria([$channelId]), $context)
            ->getEntities();

        $channel = $channels->get($channelId);

        static::assertInstanceOf(ChannelEntity::class, $channel);

        $generator = static::getContainer()->get(SeoUrlGenerator::class);

        $urls = $generator->generate(
            ids: $ids,
            template: $template,
            route: static::getContainer()->get(BlogPageSeoUrlRoute::class),
            context: $context,
            channel: $channel
        );

        $urls = iterator_to_array($urls);
        static::assertCount(\count($expected), $urls);

        foreach ($urls as $url) {
            static::assertContains($url->getSeoPathInfo(), $expected);
        }
    }
}
