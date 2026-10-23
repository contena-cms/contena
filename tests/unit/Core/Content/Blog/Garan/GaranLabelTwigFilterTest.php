<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Garan\GaranLabelDurationFormatter;
use Contena\Core\Content\Blog\Garan\GaranLabelInlineImage;
use Contena\Core\Content\Blog\Garan\GaranLabelRenderer;
use Contena\Core\Content\Blog\Garan\GaranLabelResolver;
use Contena\Core\Content\Blog\Garan\GaranLabelTwigFilter;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(GaranLabelTwigFilter::class)]
class GaranLabelTwigFilterTest extends TestCase
{
    public function testGetFiltersRegistersSwGaranLabelFilters(): void
    {
        $filter = $this->createFilter([]);

        $filters = $filter->getFilters();

        static::assertCount(8, $filters);
        static::assertSame('sw_garan_label_duration', $filters[0]->getName());
        static::assertSame('sw_garan_label', $filters[1]->getName());
        static::assertSame('sw_garan_label_nested', $filters[2]->getName());
        static::assertSame('sw_garan_label_data_uri', $filters[3]->getName());
        static::assertSame('sw_garan_label_nested_uri', $filters[4]->getName());
        static::assertSame('sw_garan_label_text_length', $filters[5]->getName());
        static::assertSame('sw_garan_label_duration_text_length', $filters[6]->getName());
        static::assertSame('sw_garan_label_mail', $filters[7]->getName());
    }

    public function testTextLengthFiltersDelegateToFitter(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->fitTextLength('Acme', 190.43, 9));
        static::assertSame(188.93, $filter->fitTextLength('Contena Lebensmittel und Nahrungsmittel GmbH', 190.43, 9));

        static::assertNull($filter->fitDurationTextLength('25', 116.4, 80, -0.03));
        static::assertSame(114.9, $filter->fitDurationTextLength('25,5', 116.4, 80, -0.03));
    }

    public function testFormatDurationDelegatesToFormatter(): void
    {
        $filter = $this->createFilter([]);

        static::assertSame('3', $filter->formatDuration(36));
        static::assertNull($filter->formatDuration(12));
        static::assertNull($filter->formatDuration(null));
    }

    public function testRenderReturnsNullForNullBlogId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->render(null, Context::createDefaultContext()));
    }

    public function testRenderReturnsNullWhenBlogNotFound(): void
    {
        $filter = $this->createFilter([new BlogCollection()]);

        static::assertNull($filter->render('blog-id', Context::createDefaultContext()));
    }

    public function testRenderReturnsNullWhenBlogNotConfirmed(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: false);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        static::assertNull($filter->render($blog->getId(), Context::createDefaultContext()));
    }

    public function testRenderReturnsSvgForCompleteConfirmedBlog(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: true);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        $svg = $filter->render($blog->getId(), Context::createDefaultContext());

        static::assertIsString($svg);
        static::assertStringContainsString('ACME-123', $svg);
    }

    public function testRenderNestedLabelReturnsNullForNullBlogId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->renderNestedLabel(null, Context::createDefaultContext()));
    }

    public function testRenderNestedLabelReturnsNullWhenBlogNotConfirmed(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: false);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        static::assertNull($filter->renderNestedLabel($blog->getId(), Context::createDefaultContext()));
    }

    public function testRenderNestedLabelReturnsSvgForCompleteConfirmedBlog(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: true);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        $svg = $filter->renderNestedLabel($blog->getId(), Context::createDefaultContext());

        static::assertIsString($svg);
        static::assertStringContainsString('nested', $svg);
        static::assertStringContainsString('3', $svg);
    }

    public function testRenderAsDataUriReturnsNullForNullBlogId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->renderAsDataUri(null, Context::createDefaultContext()));
    }

    public function testRenderAsDataUriReturnsNullWhenBlogNotConfirmed(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: false);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        static::assertNull($filter->renderAsDataUri($blog->getId(), Context::createDefaultContext()));
    }

    public function testRenderAsDataUriReturnsBase64EncodedSvgForCompleteConfirmedBlog(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: true);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        $dataUri = $filter->renderAsDataUri($blog->getId(), Context::createDefaultContext());

        static::assertIsString($dataUri);
        static::assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);

        $svg = base64_decode(substr($dataUri, \strlen('data:image/svg+xml;base64,')), true);
        static::assertIsString($svg);
        static::assertStringContainsString('ACME-123', $svg);
    }

    public function testRenderNestedAsDataUriReturnsNullForNullBlogId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->renderNestedAsDataUri(null, Context::createDefaultContext()));
    }

    public function testRenderNestedAsDataUriReturnsNullWhenBlogNotConfirmed(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: false);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        static::assertNull($filter->renderNestedAsDataUri($blog->getId(), Context::createDefaultContext()));
    }

    public function testRenderNestedAsDataUriReturnsBase64EncodedSvgForCompleteConfirmedBlog(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: true);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        $dataUri = $filter->renderNestedAsDataUri($blog->getId(), Context::createDefaultContext());

        static::assertIsString($dataUri);
        static::assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);

        $svg = base64_decode(substr($dataUri, \strlen('data:image/svg+xml;base64,')), true);
        static::assertIsString($svg);
        static::assertStringContainsString('nested', $svg);
    }

    /**
     * @deprecated tag:v6.8.0 - remove together with the `sw_garan_label_mail` filter
     */
    public function testResolveMailLabelThrowsOnceTheFilterIsRemoved(): void
    {
        $filter = $this->createFilter([]);

        $this->expectException(FeatureException::class);

        $filter->resolveMailLabel('blog-id', Context::createDefaultContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testResolveMailLabelReturnsNullForNullBlogId(): void
    {
        $filter = $this->createFilter([]);

        static::assertNull($filter->resolveMailLabel(null, Context::createDefaultContext()));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testResolveMailLabelReturnsNullWhenBlogNotConfirmed(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: false);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        static::assertNull($filter->resolveMailLabel($blog->getId(), Context::createDefaultContext()));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testResolveMailLabelLoadsTheBlogOnce(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: true);

        $filter = $this->createFilter([
            new BlogCollection([$blog]),
            static fn () => static::fail('cid and duration have to come from a single blog search'),
        ]);

        static::assertNotNull($filter->resolveMailLabel($blog->getId(), Context::createDefaultContext()));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testResolveMailLabelReferencesThePreRenderedImage(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: true);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        static::assertSame(
            ['cid' => 'cid:garan-label-nested-36.png', 'duration' => '3'],
            $filter->resolveMailLabel($blog->getId(), Context::createDefaultContext())
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testResolveMailLabelFormatsHalfYears(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: true, guaranteeMonths: 30);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        static::assertSame(
            ['cid' => 'cid:garan-label-nested-30.png', 'duration' => '2,5'],
            $filter->resolveMailLabel($blog->getId(), Context::createDefaultContext())
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testResolveMailLabelKeepsTheDurationWithoutPreRenderedImage(): void
    {
        $blog = $this->createBlog(guaranteeConfirmed: true, guaranteeMonths: 606);

        $filter = $this->createFilter([new BlogCollection([$blog])]);

        static::assertSame(
            ['cid' => null, 'duration' => '50,5'],
            $filter->resolveMailLabel($blog->getId(), Context::createDefaultContext()),
            'Legacy durations above the maximum have no image, the template falls back to the duration text'
        );
    }

    /**
     * @param list<mixed> $searchResults
     */
    private function createFilter(array $searchResults): GaranLabelTwigFilter
    {
        $twig = static::createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $template, array $context) => str_contains($template, 'nested-label')
                ? \sprintf('<svg>nested %s</svg>', $context['guarantee'])
                : \sprintf('<svg>%s %s %s</svg>', $context['manufacturer'], $context['blogNumber'], $context['guarantee'])
        );

        $resolver = new GaranLabelResolver(new GaranLabelDurationFormatter(), new GaranLabelRenderer($twig));

        /** @var StaticEntityRepository<BlogCollection> $blogRepository */
        $blogRepository = new StaticEntityRepository($searchResults, new BlogDefinition());

        return new GaranLabelTwigFilter(new GaranLabelDurationFormatter(), $blogRepository, $resolver, new GaranLabelInlineImage());
    }

    private function createBlog(bool $guaranteeConfirmed, int $guaranteeMonths = 36): BlogEntity
    {
        $manufacturer = new BlogManufacturerEntity();
        $manufacturer->setId('manufacturer-id');
        $manufacturer->setName('ACME');
        $manufacturer->setTranslated(['name' => 'ACME']);

        $blog = new BlogEntity();
        $blog->setId('blog-id');
        $blog->setManufacturer($manufacturer);
        $blog->setManufacturerNumber('ACME-123');
        $blog->setGuaranteeMonths($guaranteeMonths);
        $blog->setGuaranteeConfirmed($guaranteeConfirmed);

        return $blog;
    }
}
