<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\ContentSystem\Schema;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMap;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderMapResolver;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ContentSystemDataLoaderMapResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('the entity source can produce the channel blog entity')]
    public function testGetSourcesForChannelBlogIncludesEntity(): void
    {
        $sources = $this->resolveMap()->getSourcesFor(ChannelBlogEntity::class);

        static::assertContains(EntityLoader::SOURCE, $sources);
    }

    #[TestDox('a base property type resolves to the entity source via the channel subclass producer')]
    public function testGetSourcesForBaseBlogEntityIsSubtypeMatched(): void
    {
        // The entity loader declares ChannelBlogEntity for "blog"; a property typed with the
        // base BlogEntity must still match because the declared type is a subclass of it.
        $sources = $this->resolveMap()->getSourcesFor(BlogEntity::class);

        static::assertContains(EntityLoader::SOURCE, $sources);
    }

    #[TestDox('the entity_collection source can produce the channel blog collection')]
    public function testGetSourcesForChannelBlogCollectionIncludesEntityCollection(): void
    {
        $sources = $this->resolveMap()->getSourcesFor(ChannelBlogCollection::class);

        static::assertContains(EntityCollectionLoader::SOURCE, $sources);
    }

    #[TestDox('the entity capability for the blog carries its config seed')]
    public function testCapabilityForEntityBlogCarriesConfigSeed(): void
    {
        $map = $this->resolveMap();
        $capability = $map->capabilityFor(EntityLoader::SOURCE, ChannelBlogEntity::class);

        static::assertNotNull($capability);
        static::assertSame(['entity' => 'blog'], $capability->configTemplate);
        static::assertSame(['property'], $map->residualConfigKeysFor(EntityLoader::SOURCE, $capability));
    }

    #[TestDox('the schema endpoint exposes the capability shape per source')]
    public function testSchemaExposesCapabilityShape(): void
    {
        $generator = static::getContainer()->get(ContentSystemDataLoaderSchemaGenerator::class);
        static::assertInstanceOf(ContentSystemDataLoaderSchemaGenerator::class, $generator);

        $schema = $generator->getSchema();

        static::assertArrayHasKey(EntityLoader::SOURCE, $schema['sources']);
        $first = $schema['sources'][EntityLoader::SOURCE]['types'][0];

        // The entity source produces one capability per registered entity; each carries a concrete
        // produced class and the config seed needed to produce it.
        static::assertArrayHasKey('producedType', $first);
        static::assertTrue(class_exists($first['producedType']), $first['producedType']);
        static::assertArrayHasKey('entity', $first['configTemplate']);
        static::assertArrayHasKey('genericParameters', $first);
    }

    private function resolveMap(): ContentSystemDataLoaderMap
    {
        $resolver = static::getContainer()->get(ContentSystemDataLoaderMapResolver::class);
        static::assertInstanceOf(ContentSystemDataLoaderMapResolver::class, $resolver);

        return $resolver->resolve();
    }
}
