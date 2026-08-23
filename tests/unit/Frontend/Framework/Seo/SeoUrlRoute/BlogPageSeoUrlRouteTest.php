<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;

/**
 * @internal
 */
#[CoversClass(BlogPageSeoUrlRoute::class)]
class BlogPageSeoUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $blogDefinition = static::createStub(BlogDefinition::class);
        $route = new BlogPageSeoUrlRoute($blogDefinition);

        $config = $route->getConfig();
        static::assertSame($blogDefinition, $config->getDefinition());
        static::assertSame(BlogPageSeoUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame(BlogPageSeoUrlRoute::DEFAULT_TEMPLATE, $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
    }

    public function testCriteria(): void
    {
        $route = new BlogPageSeoUrlRoute(static::createStub(BlogDefinition::class));

        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $channel->setId('test');
        $route->prepareCriteria($criteria, $channel);

        static::assertTrue($criteria->hasEqualsFilter('active'));
        static::assertTrue($criteria->hasEqualsFilter('visibilities.channelId'));
    }

    public function testMappingWithInvalidEntity(): void
    {
        $route = new BlogPageSeoUrlRoute(static::createStub(BlogDefinition::class));

        $this->expectExceptionObject(FrontendFrameworkException::invalidArgument('SEO URL Mapping expects argument to be a BlogEntity'));
        $route->getMapping(new ArrayEntity(), new ChannelEntity());
    }

    public function testMapping(): void
    {
        $route = new BlogPageSeoUrlRoute(static::createStub(BlogDefinition::class));

        $blog = new BlogEntity();
        $blog->setId('test');
        $data = $route->getMapping($blog, new ChannelEntity());

        static::assertNull($data->getError());
        static::assertSame($blog, $data->getEntity());
        static::assertSame(['blogId' => 'test'], $data->getInfoPathContext());

        $context = $data->getSeoPathInfoContext();
        static::assertArrayHasKey('blog', $context);
        static::assertSame($blog->jsonSerialize(), $context['blog']);
    }
}
