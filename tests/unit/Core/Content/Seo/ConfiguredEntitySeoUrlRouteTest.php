<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 */
#[CoversClass(ConfiguredEntitySeoUrlRoute::class)]
class ConfiguredEntitySeoUrlRouteTest extends TestCase
{
    public function testGetConfigReturnsTheConfiguredConfig(): void
    {
        $config = $this->createConfig();
        $decorated = static::createMock(SeoUrlRouteInterface::class);
        $decorated->expects($this->once())->method('getConfig')->willReturn($config);

        static::assertSame($config, (new ConfiguredEntitySeoUrlRoute($decorated))->getConfig());
    }

    public function testDelegatesToAFullSeoUrlRoute(): void
    {
        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $entity = new PartialEntity();
        $mapping = new SeoUrlMapping($entity, [], []);

        $decorated = $this->createMock(SeoUrlRouteInterface::class);
        $decorated->expects($this->once())->method('prepareCriteria')->with($criteria, $channel);
        $decorated->expects($this->once())->method('getMapping')->with($entity, $channel)->willReturn($mapping);

        $route = new ConfiguredEntitySeoUrlRoute($decorated);
        $route->prepareCriteria($criteria, $channel);

        static::assertSame($mapping, $route->getMapping($entity, $channel));
    }

    public function testDelegatesPrepareCriteriaButFallsBackToGenericMappingForEntityRoutes(): void
    {
        $criteria = new Criteria();
        $channel = new ChannelEntity();
        $decorated = $this->createMock(BlogStoreApiUrlRoute::class);
        $decorated->expects($this->once())
            ->method('prepareCriteria')
            ->with($criteria, $channel)
            ->willReturnCallback(static function (Criteria $criteria): void {
                $criteria->addFilter(new EqualsFilter('active', true));
            });
        $decorated->expects($this->once())
            ->method('getConfig')
            ->willReturn($this->createConfig());

        $route = new ConfiguredEntitySeoUrlRoute($decorated);

        $route->prepareCriteria($criteria, $channel);
        static::assertCount(1, $criteria->getFilters());

        // getMapping falls back to a generic mapping (entity under its entity name + the primary key parameter)
        $entity = new PartialEntity();
        $entity->setUniqueIdentifier('abc123');
        $entity->assign(['name' => 'foo']);

        $mapping = $route->getMapping($entity, null);
        static::assertSame(['blogId' => 'abc123'], $mapping->getInfoPathContext());
        static::assertArrayHasKey('blog', $mapping->getSeoPathInfoContext());
        static::assertSame($entity->jsonSerialize(), $mapping->getSeoPathInfoContext()['blog']);
    }

    private function createConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(new BlogDefinition(), 'store-api.blog.detail', '', true, 'blogId');
    }
}
