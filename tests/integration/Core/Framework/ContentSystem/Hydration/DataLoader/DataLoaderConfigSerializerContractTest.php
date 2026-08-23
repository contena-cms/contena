<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogListingDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSearchDataLoader;
use Contena\Core\Content\Blog\ContentSystem\DataLoader\BlogSuggestDataLoader;
use Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader;
use Contena\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Contena\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuDataLoader;
use Contena\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Language\ContentSystem\DataLoader\LanguageDataLoader;
use Contena\Core\Test\Stub\ContentSystem\TestMultiReferenceGatingLoader;
use Contena\Core\Test\Stub\ContentSystem\TestNavigationShapedLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Enforces the round-trip contract documented on {@see AbstractContentDataLoaderConfigSerializer} for every
 * registered `content_system.config_serializer`: `encode(decode(x))` must be stable on the wire form, and
 * `encode()` must not diverge from the decoded config's `jsonSerialize()`. {@see AttributionReconciler} relies
 * on both configs it compares producing the same canonicalized `encode(decode(...))` output for the same
 * authored wiring, so a serializer that violates this contract silently drops an attribution that is still
 * honest.
 *
 * @internal
 */
class DataLoaderConfigSerializerContractTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideConfigsPerSource')]
    #[TestDox('encode(decode(x)) is stable and equals decode(x)->jsonSerialize()')]
    public function testRoundTripContractHoldsPerSource(string $source, array $config): void
    {
        $provider = $this->provider();

        $decoded = $provider->decode($source, $config);
        $encoded = $provider->encode($source, $decoded);

        static::assertSame(
            $decoded->jsonSerialize(),
            $encoded,
            \sprintf('encode() must not diverge from jsonSerialize() for source "%s".', $source),
        );

        $reEncoded = $provider->encode($source, $provider->decode($source, $encoded));

        static::assertSame(
            $encoded,
            $reEncoded,
            \sprintf('encode(decode(x)) must be stable on the wire form for source "%s".', $source),
        );
    }

    #[TestDox('the fixture set covers every registered content_system.config_serializer source')]
    public function testFixturesCoverEveryRegisteredSource(): void
    {
        $registered = array_keys($this->registeredSources());
        $fixtured = array_keys(iterator_to_array(self::provideConfigsPerSource()));

        sort($registered);
        sort($fixtured);

        static::assertSame(
            $registered,
            $fixtured,
            'A source is registered as a content_system.config_serializer but has no round-trip contract '
            . 'fixture, or vice versa. Missing fixture(s): ' . implode(', ', array_diff($registered, $fixtured))
            . '. Missing registration(s): ' . implode(', ', array_diff($fixtured, $registered)),
        );
    }

    /**
     * @return iterable<string, array{source: string, config: array<string, mixed>}>
     */
    public static function provideConfigsPerSource(): iterable
    {
        yield NavigationDataLoader::SOURCE => [
            'source' => NavigationDataLoader::SOURCE,
            'config' => ['rootId' => 'main-navigation', 'depth' => 3, 'activeProperty' => 'customActiveId'],
        ];
        yield ServiceMenuDataLoader::SOURCE => [
            'source' => ServiceMenuDataLoader::SOURCE,
            'config' => ['rootId' => 'custom-service-root'],
        ];
        yield BreadcrumbDataLoader::SOURCE => [
            'source' => BreadcrumbDataLoader::SOURCE,
            'config' => ['property' => 'entityId', 'type' => 'category', 'referrerCategoryProperty' => 'refCategoryId'],
        ];
        yield BlogListingDataLoader::SOURCE => [
            'source' => BlogListingDataLoader::SOURCE,
            'config' => ['property' => 'navigationId', 'associations' => ['tags']],
        ];
        yield BlogSearchDataLoader::SOURCE => [
            'source' => BlogSearchDataLoader::SOURCE,
            'config' => ['searchTermProperty' => 'searchTerm', 'associations' => ['tags']],
        ];
        yield BlogSuggestDataLoader::SOURCE => [
            'source' => BlogSuggestDataLoader::SOURCE,
            'config' => ['searchTermProperty' => 'searchTerm', 'associations' => ['tags']],
        ];
        yield LanguageDataLoader::SOURCE => [
            'source' => LanguageDataLoader::SOURCE,
            'config' => ['associations' => ['locale']],
        ];
        yield EntityLoader::SOURCE => [
            'source' => EntityLoader::SOURCE,
            'config' => ['entity' => 'blog', 'property' => 'name', 'associations' => ['tags']],
        ];
        yield EntityCollectionLoader::SOURCE => [
            'source' => EntityCollectionLoader::SOURCE,
            'config' => ['entity' => 'blog', 'property' => 'name', 'associations' => ['tags']],
        ];
        yield TestMultiReferenceGatingLoader::SOURCE => [
            'source' => TestMultiReferenceGatingLoader::SOURCE,
            'config' => ['entity' => 'media', 'property' => 'mediaId', 'secondProperty' => 'captionMediaId', 'activeProperty' => 'activeId'],
        ];
        yield TestNavigationShapedLoader::SOURCE => [
            'source' => TestNavigationShapedLoader::SOURCE,
            'config' => ['entity' => 'media', 'activeProperty' => 'activeId'],
        ];
    }

    private function provider(): DataLoaderConfigSerializerProvider
    {
        $provider = static::getContainer()->get(DataLoaderConfigSerializerProvider::class);
        static::assertInstanceOf(DataLoaderConfigSerializerProvider::class, $provider);

        return $provider;
    }

    /**
     * @return array<string, mixed>
     */
    private function registeredSources(): array
    {
        $locator = new \ReflectionProperty(DataLoaderConfigSerializerProvider::class, 'locator')->getValue($this->provider());
        static::assertInstanceOf(ServiceLocator::class, $locator);

        return $locator->getProvidedServices();
    }
}
