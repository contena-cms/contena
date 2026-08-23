<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\ContentSystem\SpecificationData;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\ContentSystem\StaticSpecificationSource;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RenderingSpecificationResolver::class)]
class RenderingSpecificationResolverTest extends TestCase
{
    #[TestDox('returns specification from first supporting source')]
    public function testResolveReturnsSpecificationFromFirstSupportingSource(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $path = '/blog/abc';

        $source1 = new StaticSpecificationSource(
            supports: true,
            layoutId: 'layout-1',
            specificationData: new SpecificationData([], PlaceholderValues::from([])),
        );

        $source2 = new StaticSpecificationSource(supports: false);

        $factory = new RenderingSpecificationFactory();

        $resolver = new RenderingSpecificationResolver([$source1, $source2], $factory);

        $result = $resolver->resolve($path, $request, $context);

        static::assertSame('layout-1', $result->layoutId);
        static::assertSame($request, $result->specification->request);
    }

    #[TestDox('skips sources that do not support the path')]
    public function testResolveSkipsSourcesThatDoNotSupport(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $path = '/category/xyz';

        $source1 = new StaticSpecificationSource(supports: false);

        $source2 = new StaticSpecificationSource(
            supports: true,
            layoutId: 'layout-2',
            specificationData: new SpecificationData([], PlaceholderValues::from([])),
        );

        $factory = new RenderingSpecificationFactory();

        $resolver = new RenderingSpecificationResolver([$source1, $source2], $factory);

        $result = $resolver->resolve($path, $request, $context);

        static::assertSame('layout-2', $result->layoutId);
    }

    #[TestDox('throws when no source supports the path')]
    public function testResolveThrowsNoFactoryCanHandleWhenNoSourceSupports(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $path = '/unknown/path';

        $source = new StaticSpecificationSource(supports: false);

        $factory = new RenderingSpecificationFactory();

        $resolver = new RenderingSpecificationResolver([$source], $factory);

        $this->expectExceptionObject(ContentSystemException::noFactoryCanHandle($path));

        $resolver->resolve($path, $request, $context);
    }

    #[TestDox('selects the first source matching the entity type')]
    public function testResolveWithoutLayoutSelectsSourceByEntityType(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $placeholders = PlaceholderValues::from(['blogId' => 'prod-1']);

        $categorySource = new StaticSpecificationSource(
            specificationData: new SpecificationData([], PlaceholderValues::from([])),
            targetElementId: 'category-element',
            supportedEntityType: 'category',
        );
        $blogSource = new StaticSpecificationSource(
            specificationData: new SpecificationData([], $placeholders),
            targetElementId: 'element-7',
            supportedEntityType: 'blog',
        );

        $resolver = new RenderingSpecificationResolver([$categorySource, $blogSource], new RenderingSpecificationFactory());

        $specification = $resolver->resolveWithoutLayout('blog', 'prod-1', $request, $context);

        static::assertSame($placeholders, $specification->placeholderValues);
        static::assertSame($request, $specification->request);
        static::assertSame('element-7', $specification->targetElementId);
    }

    #[TestDox('does not resolve a layout assignment')]
    public function testResolveWithoutLayoutDoesNotResolveLayoutAssignment(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();

        $source = new StaticSpecificationSource(
            specificationData: new SpecificationData([], PlaceholderValues::from([])),
            supportedEntityType: 'blog',
            failOnResolveLayoutId: true,
        );

        $resolver = new RenderingSpecificationResolver([$source], new RenderingSpecificationFactory());

        $specification = $resolver->resolveWithoutLayout('blog', 'prod-1', $request, $context);

        static::assertSame([], $specification->dataRequirements);
    }

    #[TestDox('throws unknownEntityType when no source matches the entity type')]
    public function testResolveWithoutLayoutThrowsUnknownEntityType(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();

        $source = new StaticSpecificationSource(supportedEntityType: 'blog');

        $resolver = new RenderingSpecificationResolver([$source], new RenderingSpecificationFactory());

        $this->expectExceptionObject(ContentSystemException::unknownEntityType('landing_page'));

        $resolver->resolveWithoutLayout('landing_page', 'lp-1', $request, $context);
    }
}
