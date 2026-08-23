<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\CategoryChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\LandingPageChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
class EntityRouteResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[DataProvider('frontendUrlProvider')]
    public function testGenerateFrontendUrl(string $entityName, string $expectedPath, string $id): void
    {
        if (!static::getContainer()->has(NavigationPageSeoUrlRoute::class)) {
            static::markTestSkipped('Frontend SEO URL tests need the Frontend bundle to be installed');
        }

        $resolver = $this->getContainer()->get(EntityRouteResolver::class);
        static::assertInstanceOf(EntityRouteResolver::class, $resolver);

        static::assertSame($expectedPath, $resolver->generateUrl($entityName, $id));
        static::assertSame(
            SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . $expectedPath . '#',
            $resolver->generateSeoUrlPlaceholder($entityName, $id)
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function frontendUrlProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'blog' => [BlogDefinition::ENTITY_NAME, '/blog/' . $id, $id];
        yield 'landing page' => [LandingPageDefinition::ENTITY_NAME, '/landingPage/' . $id, $id];
        yield 'category' => [CategoryDefinition::ENTITY_NAME, '/navigation/' . $id, $id];
    }

    #[DataProvider('channelApiUrlProvider')]
    public function testGenerateChannelApiUrl(string $entityName, string $expectedPath, string $id): void
    {
        $seoUrlPlaceholderHandler = $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);
        static::assertInstanceOf(SeoUrlPlaceholderHandlerInterface::class, $seoUrlPlaceholderHandler);
        $router = $this->getContainer()->get('router');
        static::assertInstanceOf(RouterInterface::class, $router);

        $resolver = new EntityRouteResolver(
            new SeoUrlRouteRegistry([]),
            $seoUrlPlaceholderHandler,
            $router,
            [
                $this->getContainer()->get(BlogChannelApiUrlRoute::class),
                $this->getContainer()->get(CategoryChannelApiUrlRoute::class),
                $this->getContainer()->get(LandingPageChannelApiUrlRoute::class),
            ],
        );

        static::assertSame($expectedPath, $resolver->generateUrl($entityName, $id));
        static::assertSame(
            SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . $expectedPath . '#',
            $resolver->generateSeoUrlPlaceholder($entityName, $id)
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function channelApiUrlProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'blog' => [BlogDefinition::ENTITY_NAME, '/channel-api/blog/' . $id, $id];
        yield 'landing page' => [LandingPageDefinition::ENTITY_NAME, '/channel-api/landing-page/' . $id, $id];
        yield 'category' => [CategoryDefinition::ENTITY_NAME, '/channel-api/category/' . $id, $id];
    }
}
