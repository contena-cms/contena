<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Garan\GaranLabelResolver;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(GaranLabelResolver::class)]
class GaranLabelResolverTest extends TestCase
{
    public function testResolvesToNullWhenGuaranteeNotConfirmed(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: false, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        static::assertNull($this->createResolver()->resolve($blog));
    }

    public function testResolvesToNullWhenManufacturerNumberMissing(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: true, manufacturerNumber: null, guaranteeMonths: 36);

        static::assertNull($this->createResolver()->resolve($blog));
    }

    public function testResolvesToNullWhenDurationInvalid(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 12);

        static::assertNull($this->createResolver()->resolve($blog));
    }

    public function testResolvesToNullWhenManufacturerNameMissing(): void
    {
        $blog = self::createBlog(
            guaranteeConfirmed: true,
            manufacturerNumber: 'ACME-123',
            guaranteeMonths: 36,
            manufacturer: self::createManufacturer(name: null, translatedName: null)
        );

        static::assertNull($this->createResolver()->resolve($blog));
    }

    public function testResolvesLabelWhenManufacturerNameIsOnlyAvailableThroughTranslationFallback(): void
    {
        $blog = self::createBlog(
            guaranteeConfirmed: true,
            manufacturerNumber: 'ACME-123',
            guaranteeMonths: 36,
            manufacturer: self::createManufacturer(name: null, translatedName: 'ACME')
        );

        $svg = $this->createResolver()->resolve($blog);

        static::assertIsString($svg);
        static::assertStringContainsString('ACME', $svg);
    }

    public function testResolvesNestedLabelWhenManufacturerNameIsOnlyAvailableThroughTranslationFallback(): void
    {
        $blog = self::createBlog(
            guaranteeConfirmed: true,
            manufacturerNumber: 'ACME-123',
            guaranteeMonths: 36,
            manufacturer: self::createManufacturer(name: null, translatedName: 'ACME')
        );

        $svg = $this->createResolver()->resolve($blog, GaranLabelResolver::LABEL_TYPE_NESTED);

        static::assertIsString($svg);
        static::assertStringContainsString('nested', $svg);
    }

    public function testResolvesToSvgForCompleteConfirmedBlog(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        $svg = $this->createResolver()->resolve($blog);

        static::assertIsString($svg);
        static::assertStringContainsString('ACME-123', $svg);
        static::assertStringContainsString('ACME', $svg);
        static::assertStringContainsString('3', $svg);
    }

    public function testResolveDefaultsToFullLabelType(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        $svg = $this->createResolver()->resolve($blog);

        static::assertIsString($svg);
        static::assertStringNotContainsString('nested', $svg);
    }

    public function testResolvesNestedLabelToNullWhenGuaranteeNotConfirmed(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: false, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        static::assertNull($this->createResolver()->resolve($blog, GaranLabelResolver::LABEL_TYPE_NESTED));
    }

    public function testResolvesNestedLabelToNullWhenManufacturerNumberMissing(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: true, manufacturerNumber: null, guaranteeMonths: 36);

        static::assertNull($this->createResolver()->resolve($blog, GaranLabelResolver::LABEL_TYPE_NESTED));
    }

    public function testResolvesNestedLabelToNullWhenDurationInvalid(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 12);

        static::assertNull($this->createResolver()->resolve($blog, GaranLabelResolver::LABEL_TYPE_NESTED));
    }

    public function testResolvesNestedLabelToSvgForCompleteConfirmedBlog(): void
    {
        $blog = self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 36);

        $svg = $this->createResolver()->resolve($blog, GaranLabelResolver::LABEL_TYPE_NESTED);

        static::assertIsString($svg);
        static::assertStringContainsString('nested', $svg);
        static::assertStringContainsString('3', $svg);
    }

    /**
     * @return \Generator<string, array{BlogEntity, string|null}>
     */
    public static function resolveDurationProvider(): \Generator
    {
        yield 'guarantee not confirmed' => [self::createBlog(guaranteeConfirmed: false, manufacturerNumber: 'ACME-123', guaranteeMonths: 36), null];
        yield 'manufacturer number missing' => [self::createBlog(guaranteeConfirmed: true, manufacturerNumber: ' ', guaranteeMonths: 36), null];
        yield 'manufacturer name missing' => [
            self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 36, manufacturer: self::createManufacturer(name: null, translatedName: null)),
            null,
        ];
        yield 'duration invalid' => [self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 20), null];
        yield 'whole years' => [self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 36), '3'];
        yield 'half years' => [self::createBlog(guaranteeConfirmed: true, manufacturerNumber: 'ACME-123', guaranteeMonths: 30), '2,5'];
    }

    #[DataProvider('resolveDurationProvider')]
    public function testResolveDurationFollowsTheLabelRules(BlogEntity $blog, ?string $expected): void
    {
        static::assertSame($expected, $this->createResolver()->resolveDuration($blog));
    }

    #[DataProvider('resolveDurationProvider')]
    public function testResolveSnapshotIsNullExactlyWhenThereIsNoLabel(BlogEntity $blog, ?string $expectedDuration): void
    {
        static::assertSame($expectedDuration === null, $this->createResolver()->resolveSnapshot($blog) === null);
    }

    public function testResolveSnapshotHoldsTheLabelValues(): void
    {
        $blog = self::createBlog(
            guaranteeConfirmed: true,
            manufacturerNumber: ' ACME-123 ',
            guaranteeMonths: 30,
            manufacturer: self::createManufacturer(name: null, translatedName: ' ACME ')
        );

        static::assertSame(
            ['guaranteeMonths' => 30, 'brand' => 'ACME', 'modelIdentifier' => 'ACME-123'],
            $this->createResolver()->resolveSnapshot($blog)
        );
    }



}
