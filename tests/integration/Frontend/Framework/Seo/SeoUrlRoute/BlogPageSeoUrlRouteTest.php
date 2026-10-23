<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
class BlogPageSeoUrlRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    public function testMainCategories(): void
    {
        $ids = new IdsCollection();

        // Frontend-type sales channels: SEO URLs are generated directly (headless channels only generate for
        // domains marked as external frontend, which is covered in the headless SEO tests).
        $channel1 = $this->createChannel(['domains' => [['url' => 'http://sw-seo-main-category-1.test']]]);
        $channel2 = $this->createChannel(['domains' => [['url' => 'http://sw-seo-main-category-2.test']]]);

        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->visibility($channel1['id'])
            ->visibility($channel2['id'])
            ->categories(['c1', 'c2'])
            ->mainCategory($channel1['id'], 'c1')
            ->mainCategory($channel2['id'], 'c2')
            ->build();

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->generateAndAssert(
            ids: array_values($ids->getList(['p1'])),
            template: '{{ blog.mainCategories.first.category.translated.name }}',
            channelId: $channel1['id'],
            expected: ['c1']
        );

        $this->generateAndAssert(
            ids: array_values($ids->getList(['p1'])),
            template: '{{ blog.mainCategories.first.category.translated.name }}',
            channelId: $channel2['id'],
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
