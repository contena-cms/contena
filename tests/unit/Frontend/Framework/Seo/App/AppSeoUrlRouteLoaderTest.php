<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityWriterGateway;
use Contena\Frontend\Framework\Seo\App\AppEntitySeoUrlConfig;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlRouteLoader;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlRouteProvider;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(AppSeoUrlRouteLoader::class)]
class AppSeoUrlRouteLoaderTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class],
            Validation::createValidator(),
            new StaticEntityWriterGateway()
        );
    }

    public function testEveryEntitySeoUrlIsWrappedIntoAConfiguredSeoUrlRoute(): void
    {
        $loader = $this->loader($this->seoUrl('blog-teaser', 'blog'));

        $routes = iterator_to_array($loader->load(), false);

        static::assertCount(1, $routes);
        static::assertInstanceOf(ConfiguredEntitySeoUrlRoute::class, $routes[0]);

        $config = $routes[0]->getConfig();
        static::assertSame($this->definitionRegistry->getByEntityName('blog'), $config->getDefinition());
        static::assertSame('frontend.app.SwagSeoUrlApp.blog-teaser', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
        static::assertSame('{{ blog.translated.name }}', $config->getTemplate());
        static::assertSame(['hook' => 'blog-teaser', 'id' => 'the-id'], $config->getPrimaryKeyParameter('the-id'));
    }

    public function testSeoUrlsBoundToAnUnknownEntityAreSkipped(): void
    {
        $loader = $this->loader(
            $this->seoUrl('blog-detail', 'ce_blog'),
            $this->seoUrl('blog-teaser', 'blog')
        );

        $routes = iterator_to_array($loader->load(), false);

        static::assertCount(1, $routes);
        static::assertSame('frontend.app.SwagSeoUrlApp.blog-teaser', $routes[0]->getConfig()->getRouteName());
    }

    public function testWithoutEntitySeoUrlsNothingIsLoaded(): void
    {
        static::assertSame([], iterator_to_array($this->loader()->load(), false));
    }

    private function loader(AppEntitySeoUrlConfig ...$seoUrls): AppSeoUrlRouteLoader
    {
        $provider = static::createStub(AppSeoUrlRouteProvider::class);
        $provider->method('getEntityRoutes')->willReturn(array_values($seoUrls));

        return new AppSeoUrlRouteLoader($provider, $this->definitionRegistry);
    }

    private function seoUrl(string $name, string $entityName): AppEntitySeoUrlConfig
    {
        return new AppEntitySeoUrlConfig(
            name: $name,
            routeName: 'frontend.app.SwagSeoUrlApp.' . $name,
            hook: $name,
            entityName: $entityName,
            defaultTemplate: '{{ ' . $entityName . '.translated.name }}',
        );
    }
}
