<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Api;

use Contena\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Diagnostics\ViolationCode;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Contena\Core\Framework\ContentSystem\Layout\StoredTreeStyleNormalizer;
use Contena\Core\Framework\ContentSystem\Validation\ViolationConstraintMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(DraftLayoutDecoder::class)]
class DraftLayoutDecoderTest extends TestCase
{
    #[TestDox('decode returns the decoded element tree with properties wrapped into the storage value envelope')]
    public function testDecodeReturnsTreeWithPropertiesWrapped(): void
    {
        $tree = $this->decoder()->decode([['id' => 'el-1', 'component' => 'Ct:Block', 'properties' => ['headline' => 'Hi']]]);

        static::assertCount(1, $tree);
        static::assertSame('el-1', $tree[0]->id);
        static::assertSame('Ct:Block', $tree[0]->component);
        static::assertSame('Hi', $tree[0]->property('headline')?->jsonSerialize());
    }

    #[TestDox('decodeOne returns the single decoded element for a structurally valid element')]
    public function testDecodeOneReturnsElement(): void
    {
        $element = $this->decoder()->decodeOne(['id' => 'el-1', 'component' => 'Ct:Block']);

        static::assertSame('el-1', $element->id);
    }

    #[TestDox('decodeLintable returns the decoded tree and no violations for a valid layout')]
    public function testDecodeLintableReturnsTreeWithoutViolations(): void
    {
        [$tree, $violations] = $this->decoder()->decodeLintable([['id' => 'el-1', 'component' => 'Ct:Block']]);

        static::assertSame(['el-1'], array_map(static fn (StoredElement $e): string => $e->id, $tree));
        static::assertSame([], $violations);
    }

    #[TestDox('decode canonicalises the style of a decoded element through the style normalizer')]
    public function testDecodeNormalizesElementStyle(): void
    {
        $tree = $this->decoder()->decode([[
            'id' => 'el-1',
            'component' => 'Ct:Block',
            'style' => ['align-self' => ['xs' => 'center']],
        ]]);

        static::assertSame(
            ['align-self' => ['xs' => 'center', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $tree[0]->style->toArray(),
        );
    }

    #[TestDox('decode canonicalises the style of a slot child, not only of the root element')]
    public function testDecodeNormalizesSlotChildStyle(): void
    {
        $tree = $this->decoder()->decode([[
            'id' => 'root',
            'component' => 'Ct:Block',
            'slots' => ['content' => [[
                'id' => 'child',
                'component' => 'Ct:Text',
                'style' => ['align-self' => ['xs' => 'end']],
            ]]],
        ]]);

        static::assertSame(
            ['align-self' => ['xs' => 'end', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $tree[0]->slots['content'][0]->style->toArray(),
        );
    }

    #[TestDox('decodeLintable canonicalises style too, so the diagnose route sees the saved shape')]
    public function testDecodeLintableNormalizesElementStyle(): void
    {
        [$tree, $violations] = $this->decoder()->decodeLintable([[
            'id' => 'el-1',
            'component' => 'Ct:Block',
            'style' => ['align-self' => ['xs' => 'center']],
        ]]);

        static::assertSame([], $violations);
        static::assertSame(
            ['align-self' => ['xs' => 'center', 'sm' => 'auto', 'md' => 'auto', 'lg' => 'auto', 'xl' => 'auto', 'xxl' => 'auto']],
            $tree[0]->style->toArray(),
        );
    }

    #[TestDox('decodeLintable keeps a duplicate-id tree so the diagnostics pass can report it, instead of rejecting')]
    public function testDecodeLintableKeepsDuplicateIdTreeForDiagnostics(): void
    {
        [$tree, $violations] = $this->decoder()->decodeLintable([
            ['id' => 'dup', 'component' => 'Ct:Block'],
            ['id' => 'dup', 'component' => 'Ct:Other'],
        ]);

        static::assertSame(['dup', 'dup'], array_map(static fn (StoredElement $e): string => $e->id, $tree));
        static::assertSame([], $violations);
    }

    #[TestDox('decodeLintable collects a client-defect as an invalid_config violation and keeps the rest of the tree')]
    public function testDecodeLintableCollectsClientDefect(): void
    {
        $decoder = $this->decoder($this->configProviderThrowing(ContentSystemException::unknownLoaderEntity('prodct')));

        [$tree, $violations] = $decoder->decodeLintable([
            $this->elementWithDataRequirement('bad'),
            ['id' => 'good', 'component' => 'Ct:Block'],
        ]);

        static::assertCount(1, $tree);
        static::assertSame('good', $tree[0]->id);
        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::InvalidConfig, $violations[0]->code);
        static::assertSame('bad', $violations[0]->elementId);
    }

    /**
     * @param array<string, mixed> $wiring
     */
    #[DataProvider('elementLocalWiringDefectProvider')]
    #[TestDox('decodeLintable reports $_dataName as an invalid_config violation on its element')]
    public function testDecodeLintableCollectsAnElementLocalWiringDefect(array $wiring, string $expectedMessageFragment): void
    {
        [$tree, $violations] = $this->decoder()->decodeLintable([
            ['id' => 'defective', 'component' => 'Ct:Block', ...$wiring],
            ['id' => 'good', 'component' => 'Ct:Block'],
        ]);

        static::assertSame(['good'], array_map(static fn (StoredElement $e): string => $e->id, $tree));
        static::assertCount(1, $violations);
        static::assertSame(ViolationCode::InvalidConfig, $violations[0]->code);
        static::assertSame('defective', $violations[0]->elementId);
        static::assertStringContainsString($expectedMessageFragment, $violations[0]->message);
    }

    #[TestDox('decode rejects a tree nested past the codec maximum depth')]
    public function testDecodeRejectsExcessiveNestingDepth(): void
    {
        $element = ['id' => 'leaf', 'component' => 'Ct:Block'];
        for ($level = 0; $level < 60; ++$level) {
            $element = ['id' => 'n' . $level, 'component' => 'Ct:Block', 'slots' => ['content' => [$element]]];
        }

        try {
            $this->decoder()->decode([$element]);
            static::fail('Expected a ContentSystemException for the over-deep tree.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('element nesting at most ' . StoredElementCodec::MAX_NESTING_DEPTH . ' levels deep', $exception->getMessage());
        }
    }

    #[TestDox('decodeOne throws invalidLayoutStructure for a malformed element instead of a codec error')]
    public function testDecodeOneRejectsMalformedElement(): void
    {
        try {
            $this->decoder()->decodeOne(['component' => 'Ct:Block']);
            static::fail('Expected a ContentSystemException for the malformed element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('Layout element id must be a non-empty string.', $exception->getMessage());
        }
    }

    /**
     * @param array<int|string, mixed> $rawLayout
     */
    #[DataProvider('invalidLayoutProvider')]
    #[TestDox('decode rejects a structurally invalid layout with a precise violation list')]
    public function testDecodeRejectsInvalidLayout(array $rawLayout, ConstraintViolationList $expectedViolations): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidLayoutStructure($expectedViolations));

        $this->decoder()->decode($rawLayout);
    }

    #[TestDox('decode rejects a globally duplicate element id, reading the rule off the stored forest')]
    public function testDecodeRejectsDuplicateElementId(): void
    {
        try {
            $this->decoder()->decode([
                ['id' => 'dup', 'component' => 'Ct:Block'],
                ['id' => 'dup', 'component' => 'Ct:Other'],
            ]);
            static::fail('Expected a ContentSystemException for the duplicate element id.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('Element id "dup" is not unique across the layout.', $exception->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $malformedElement
     */
    #[DataProvider('malformedContainerProvider')]
    #[TestDox('decode refuses a malformed nested container instead of silently emptying it')]
    public function testDecodeRefusesMalformedContainer(array $malformedElement): void
    {
        try {
            $this->decoder()->decode([$malformedElement]);
            static::fail('Expected a ContentSystemException for the malformed container.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('decode aggregates a client-defect decode failure into a 400 invalidLayoutStructure')]
    public function testDecodeRewrapsClientDefect(): void
    {
        $decoder = $this->decoder($this->configProviderThrowing(ContentSystemException::unknownLoaderEntity('prodct')));

        try {
            $decoder->decode([$this->elementWithDataRequirement('el-1')]);
            static::fail('Expected a ContentSystemException for the client-defect decode failure.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
        }
    }

    #[TestDox('decode refuses a numeric wiring key as a client defect rather than letting it escape as an internal fault')]
    public function testDecodeRefusesNumericWiringKeyAsClientDefect(): void
    {
        try {
            $this->decoder()->decode([['id' => 'el-1', 'component' => 'Ct:Block', 'properties' => ['5' => 'x']]]);
            static::fail('Expected a ContentSystemException for the numeric property key.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }

    /**
     * The three element-local wiring codes are client-defect codes, so they take the two draft-route paths the
     * other codec defects take rather than escaping as a fault: aggregated into the strict 400 here, collected
     * as a per-element `invalid_config` violation by {@see testDecodeLintableCollectsAnElementLocalWiringDefect()}.
     *
     * @param array<string, mixed> $wiring
     */
    #[DataProvider('elementLocalWiringDefectProvider')]
    #[TestDox('decode rejects $_dataName as a 400 invalidLayoutStructure')]
    public function testDecodeRejectsAnElementLocalWiringDefect(array $wiring, string $expectedMessageFragment): void
    {
        try {
            $this->decoder()->decode([['id' => 'el-1', 'component' => 'Ct:Block', ...$wiring]]);
            static::fail('Expected a ContentSystemException for the element-local wiring defect.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString($expectedMessageFragment, $exception->getMessage());
        }
    }

    #[TestDox('decode rethrows a non-client-defect decode fault unchanged')]
    public function testDecodeRethrowsInternalFault(): void
    {
        $decoder = $this->decoder($this->configProviderThrowing(ContentSystemException::layoutNotFound('x')));

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $decoder->decode([$this->elementWithDataRequirement('el-1')]);
    }

    #[TestDox('decodeLintable still throws invalidLayoutStructure for a structurally invalid element')]
    public function testDecodeLintableRejectsStructurallyInvalidElement(): void
    {
        try {
            $this->decoder()->decodeLintable([['component' => 'Ct:Block']]);
            static::fail('Expected a ContentSystemException for the structurally invalid element.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertStringContainsString('Layout element id must be a non-empty string.', $exception->getMessage());
        }
    }

    #[TestDox('decodeLintable rethrows a non-client-defect decode fault unchanged')]
    public function testDecodeLintableRethrowsInternalFault(): void
    {
        $decoder = $this->decoder($this->configProviderThrowing(ContentSystemException::layoutNotFound('x')));

        $this->expectExceptionObject(ContentSystemException::layoutNotFound('x'));
        $decoder->decodeLintable([$this->elementWithDataRequirement('el-1')]);
    }

    /**
     * @return iterable<string, array{array<int|string, mixed>, ConstraintViolationList}>
     */
    public static function invalidLayoutProvider(): iterable
    {
        yield 'non-array top-level element' => [
            ['not-an-array'],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element must be an array.', null, [], null, '[0]', 'not-an-array'),
            ]),
        ];

        yield 'element missing both id and component aggregates both violations' => [
            [[]],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id must be a non-empty string.', null, [], null, '[0].id', null),
                new ConstraintViolation('Layout element component must be a non-empty string.', null, [], null, '[0].component', null),
            ]),
        ];

        yield 'element missing only the id' => [
            [['component' => 'Ct:Block']],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element id must be a non-empty string.', null, [], null, '[0].id', null),
            ]),
        ];

        yield 'element missing only the component' => [
            [['id' => 'el-1']],
            new ConstraintViolationList([
                new ConstraintViolation('Layout element component must be a non-empty string.', null, [], null, '[0].component', null),
            ]),
        ];
    }

    /**
     * A scalar `style` is the case the older decode path emptied rather than refused, which took the element's
     * style out of reach of the unknown-style-option diagnostic entirely. It is listed here alongside the other
     * containers because the codec judges all of them by the same rule.
     *
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function malformedContainerProvider(): iterable
    {
        yield 'scalar style' => [['id' => 'root', 'component' => 'Ct:Block', 'style' => 'garbage']];
        yield 'scalar slots' => [['id' => 'root', 'component' => 'Ct:Block', 'slots' => 'garbage']];
        yield 'non-list slot children container' => [['id' => 'root', 'component' => 'Ct:Block', 'slots' => ['main' => 'garbage']]];
        yield 'non-array nested child' => [['id' => 'root', 'component' => 'Ct:Block', 'slots' => ['content' => ['not-an-array']]]];
        yield 'scalar dataRequirements' => [['id' => 'root', 'component' => 'Ct:Block', 'dataRequirements' => 'garbage']];
        yield 'scalar acceptsContext' => [['id' => 'root', 'component' => 'Ct:Block', 'acceptsContext' => 'garbage']];
        yield 'scalar attributedSpecifications' => [['id' => 'root', 'component' => 'Ct:Block', 'attributedSpecifications' => 'garbage']];
    }

    /**
     * Each fragment carries the interpolated context keys, not only the rule's placeholder-free prose tail. The
     * three rules all surface as the same error code, so the identifiers are what tells one apart from another:
     * a fragment stopping at the shared tail would pass while the wrong rule fired on the wrong key.
     *
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function elementLocalWiringDefectProvider(): iterable
    {
        yield 'two consumers sharing one base key' => [
            ['acceptsContext' => [
                'blog' => ['type' => 'single', 'required' => true],
                'category' => ['type' => 'single', 'required' => true, 'propertyAlias' => 'blog'],
            ]],
            'Property key "blog" is used by both context "blog" and "category".',
        ];

        yield 'a redistributing consumer keyed by a dotted path' => [
            ['acceptsContext' => [
                'blog.manufacturer' => ['type' => 'single', 'required' => true, 'redistribute' => true],
            ]],
            'Context key "blog.manufacturer" uses dot notation and cannot be redistributed.',
        ];

        yield 'a redistributing consumer whose derived key an authored provider holds' => [
            [
                'providesContext' => ['blog' => ['type' => 'single', 'distribution' => 'broadcast']],
                'acceptsContext' => ['blog' => ['type' => 'single', 'required' => true, 'redistribute' => true]],
            ],
            'Context key "blog" has both redistribute:true and explicit providesContext.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function elementWithDataRequirement(string $id): array
    {
        return [
            'id' => $id,
            'component' => 'Ct:Block',
            'dataRequirements' => ['blog' => ['source' => 'entity', 'config' => ['entity' => 'prodct']]],
        ];
    }

    private function configProviderThrowing(ContentSystemException $exception): DataLoaderConfigSerializerProvider
    {
        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willThrowException($exception);

        return $provider;
    }

    private function configProviderDecoding(): DataLoaderConfigSerializerProvider
    {
        $provider = static::createStub(DataLoaderConfigSerializerProvider::class);
        $provider->method('decode')->willReturn(static::createStub(AbstractContentDataLoaderConfig::class));

        return $provider;
    }

    private function decoder(?DataLoaderConfigSerializerProvider $configProvider = null): DraftLayoutDecoder
    {
        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn([
            'align-self' => new StyleOptionSpecification(
                'align-self',
                new StyleOptionValueType('string', ['auto', 'start', 'center', 'end'], null, null, 'auto'),
                true,
                null,
                'core',
            ),
        ]);

        return new DraftLayoutDecoder(
            new StoredElementCodec($configProvider ?? $this->configProviderDecoding()),
            new StoredTreeStyleNormalizer(new ElementStyleNormalizer($registry, new BoxSpacingNormalizer())),
            new ViolationConstraintMapper(),
        );
    }
}
