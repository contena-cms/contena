<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\CategoryStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\LandingPageStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Symfony\Component\Routing\RouterInterface;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

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
            static::markTestSkipped('Frontend seo url tests need frontend bundle to be installed');
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

        yield 'blog' => [BlogDefinition::ENTITY_NAME, '/detail/' . $id, $id];
        yield 'landing page' => [LandingPageDefinition::ENTITY_NAME, '/landingPage/' . $id, $id];
        yield 'category' => [CategoryDefinition::ENTITY_NAME, '/navigation/' . $id, $id];
    }

    #[DataProvider('storeApiUrlProvider')]
    public function testGenerateStoreApiUrl(string $entityName, string $expectedPath, string $id): void
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
                $this->getContainer()->get(BlogStoreApiUrlRoute::class),
                $this->getContainer()->get(CategoryStoreApiUrlRoute::class),
                $this->getContainer()->get(LandingPageStoreApiUrlRoute::class),
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
    public static function storeApiUrlProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'blog' => [BlogDefinition::ENTITY_NAME, '/store-api/blog/' . $id, $id];
        yield 'landing page' => [LandingPageDefinition::ENTITY_NAME, '/store-api/landing-page/' . $id, $id];
        yield 'category' => [CategoryDefinition::ENTITY_NAME, '/store-api/category/' . $id, $id];
    }
}
