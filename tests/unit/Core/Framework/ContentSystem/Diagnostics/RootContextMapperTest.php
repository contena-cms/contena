<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\ContentSystem\Layout\Scaffolding\VirtualRootWrapper;

/**
 * @internal
 */
#[CoversClass(RootContextMapper::class)]
class RootContextMapperTest extends TestCase
{
    #[TestDox('maps a page requirement to a broadcast single root context with the loader-resolved FQCN')]
    public function testMapsRequirementToRootContext(): void
    {
        $requirement = new DataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));

        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willReturn(ChannelBlogEntity::class);

        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        $contexts = new RootContextMapper($provider)->map([$requirement]);

        static::assertCount(1, $contexts);
        static::assertSame('blog', $contexts[0]->contextKey);
        static::assertSame(ChannelBlogEntity::class, $contexts[0]->fqcn);
        static::assertSame(ContextType::Single, $contexts[0]->contextType);
        static::assertSame(DistributionStrategy::Broadcast, $contexts[0]->distribution);
        static::assertSame(VirtualRootWrapper::VIRTUAL_ROOT_ID, $contexts[0]->providerElementId);
    }

    #[TestDox('maps an empty requirement set to no root context')]
    public function testEmptyRequirementsMapToEmptyRootContext(): void
    {
        $provider = static::createStub(DataLoaderProvider::class);

        static::assertSame([], new RootContextMapper($provider)->map([]));
    }

    #[TestDox('propagates an unknown-entity exception without swallowing it')]
    public function testResolveTypePropagatesException(): void
    {
        $loader = static::createStub(AbstractContentDataLoader::class);
        $loader->method('resolveProducedType')->willThrowException(ContentSystemException::unknownLoaderEntity('prodct'));

        $provider = static::createStub(DataLoaderProvider::class);
        $provider->method('get')->willReturn($loader);

        $requirement = new DataRequirement('blog', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));

        $this->expectExceptionObject(ContentSystemException::unknownLoaderEntity('prodct'));

        new RootContextMapper($provider)->resolveType($requirement);
    }
}
