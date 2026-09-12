<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Api;

use Contena\Core\Defaults;
use Contena\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Contena\Core\Framework\ContentSystem\Binding\AttributionReconciler;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Contena\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Breakpoint;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Contena\Core\Framework\ContentSystem\Layout\LayoutDefaultSeeder;
use Contena\Core\Framework\ContentSystem\Layout\LayoutWriteBoundary;
use Contena\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Contena\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use Contena\Core\Framework\Log\Package;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DraftLayoutDecoder::class)]
class DraftLayoutStyleParityTest extends TestCase
{
    private const ELEMENT_ID = 'parity-element';

    #[TestDox('yields the same style shape from the draft decode path as the write boundary produces for the same raw element')]
    public function testDraftDecodeMatchesWriteBoundaryStyle(): void
    {
        // A partially specified breakpoint map of an option that declares a default: the normalizer fills the
        // missing breakpoints, so a path that skipped it would produce a visibly different map.
        $raw = [[
            'id' => self::ELEMENT_ID,
            'component' => 'Ct:Content:Text',
            'properties' => ['text' => [Defaults::LANGUAGE_SYSTEM => '<p>Parity</p>']],
            'style' => ['align-self' => ['xs' => 'center']],
        ]];

        // One normalizer instance feeds both paths, so a path that stops calling it is what the comparison
        // catches. Which instance the container hands each service is a wiring question this test does not make.
        $normalizer = new StoredTreeStyleNormalizer($this->styleNormalizer());
        $elementCodec = new StoredElementCodec(static::createStub(DataLoaderConfigSerializerProvider::class));

        $draftStyle = new DraftLayoutDecoder($elementCodec, $normalizer, new ViolationConstraintMapper())
            ->decode($raw)[0]->style->toArray();

        $written = $this->boundary($normalizer)->apply(new StoredTreeCodec($elementCodec)->decode($raw));
        $writtenStyle = $written->roots[0]->style->toArray();

        static::assertSame($writtenStyle, $draftStyle);

        // Guards the equivalence against being vacuously true on an untouched map.
        static::assertIsArray($draftStyle['align-self']);
        static::assertSame(Breakpoint::values(), array_keys($draftStyle['align-self']));
    }

    /**
     * Seeding and attribution reconciliation pass through: both act on properties and wiring, so neither
     * reaches the style map this test compares.
     */
    private function boundary(StoredTreeStyleNormalizer $normalizer): LayoutWriteBoundary
    {
        $seeder = static::createStub(LayoutDefaultSeeder::class);
        $seeder->method('seed')->willReturnArgument(0);

        $reconciler = static::createStub(AttributionReconciler::class);
        $reconciler->method('reconcile')->willReturnArgument(0);

        return new LayoutWriteBoundary($seeder, $normalizer, $reconciler);
    }

    /**
     * A registry holding the one breakpoint-aware option this test styles, declared with a default so the
     * partial map expands.
     */
    private function styleNormalizer(): ElementStyleNormalizer
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'align-self' => new StyleOptionSpecification(
                'align-self',
                new StyleOptionValueType(StyleOptionValueType::TYPE_STRING, ['auto', 'start', 'center', 'end'], null, null, 'auto'),
                true,
                null,
                'core',
            ),
        ]);

        return new ElementStyleNormalizer($registry, new BoxSpacingNormalizer());
    }
}
