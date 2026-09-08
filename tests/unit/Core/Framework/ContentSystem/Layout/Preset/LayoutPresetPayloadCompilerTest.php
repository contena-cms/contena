<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset;

use Contena\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LayoutPresetPayloadCompiler::class)]
class LayoutPresetPayloadCompilerTest extends TestCase
{
    #[TestDox('carries the component through, mints a hex id, and copies properties verbatim')]
    public function testCompileCarriesComponentMintsIdAndCopiesProperties(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([
            ['component' => 'CT:Content:Text', 'properties' => ['text' => '<p>hi</p>']],
        ]);

        static::assertCount(1, $captured);
        static::assertSame('CT:Content:Text', $captured[0]['component']);
        static::assertSame(['text' => '<p>hi</p>'], $captured[0]['properties']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $captured[0]['id']);
    }

    #[TestDox('recurses into slots keyed by slot name, minting ids at every level')]
    public function testCompileRecursesIntoSlots(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([
            [
                'component' => 'CT:Grid:Container',
                'slots' => [
                    'content' => [
                        ['component' => 'CT:Media:Image'],
                        ['component' => 'CT:Content:Text', 'properties' => ['text' => 'x']],
                    ],
                ],
            ],
        ]);

        $container = $captured[0];
        static::assertSame('CT:Grid:Container', $container['component']);
        static::assertArrayHasKey('content', $container['slots']);

        $children = $container['slots']['content'];
        static::assertCount(2, $children);
        static::assertSame('CT:Media:Image', $children[0]['component']);
        static::assertSame('CT:Content:Text', $children[1]['component']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $children[0]['id']);
        static::assertNotSame($container['id'], $children[0]['id']);
    }

    #[TestDox('carries per-viewport style through to the draft element')]
    public function testCompileCarriesStyle(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $style = [
            'col-span' => ['xs' => 4, 'sm' => 4, 'md' => 4, 'lg' => 3, 'xl' => 3, 'xxl' => 3],
            'display' => ['xs' => false, 'sm' => false, 'md' => false, 'lg' => true, 'xl' => true, 'xxl' => true],
        ];

        $compiler->compile([
            ['component' => 'CT:Blog:Listing', 'style' => $style],
        ]);

        static::assertSame($style, $captured[0]['style']);
    }

    #[TestDox('throws when style is not a mapping')]
    public function testNonArrayStyleThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'CT:Content:Text', 'style' => 'nope']]);
    }

    #[TestDox('accepts every canonical breakpoint key in a style option')]
    public function testCompileAcceptsCanonicalBreakpoints(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $style = ['col-span' => ['xs' => 4, 'sm' => 4, 'md' => 4, 'lg' => 3, 'xl' => 3, 'xxl' => 3]];

        $compiler->compile([['component' => 'CT:Blog:Listing', 'style' => $style]]);

        static::assertSame($style, $captured[0]['style']);
    }

    #[TestDox('throws on a style breakpoint key outside the canonical set')]
    public function testUnknownStyleBreakpointThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'CT:Blog:Listing', 'style' => ['col-span' => ['lg' => 3, 'nope' => 2]]]]);
    }

    #[TestDox('throws when a breakpoint mapping does not define every breakpoint')]
    public function testIncompleteBreakpointMapThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'CT:Blog:Listing', 'style' => ['col-span' => ['lg' => 3, 'md' => 4]]]]);
    }

    #[TestDox('broadcasts a scalar style option value across every breakpoint')]
    public function testScalarStyleOptionBroadcastsToAllBreakpoints(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([['component' => 'CT:Blog:Listing', 'style' => ['col-span' => 3]]]);

        static::assertSame(
            ['xs' => 3, 'sm' => 3, 'md' => 3, 'lg' => 3, 'xl' => 3, 'xxl' => 3],
            $captured[0]['style']['col-span'],
        );
    }

    #[TestDox('re-encodes the decoded elements into the served payload')]
    public function testCompileEncodesDecodedElements(): void
    {
        $decoder = static::createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturn([new StoredElement('el-1', 'CT:Content:Text')]);

        $result = $this->createCompiler($decoder)->compile([['component' => 'CT:Content:Text']]);

        static::assertSame([
            ['id' => 'el-1', 'component' => 'CT:Content:Text', 'properties' => []],
        ], $result);
    }

    #[TestDox('an empty layout compiles to an empty payload')]
    public function testEmptyLayoutCompilesToEmptyPayload(): void
    {
        $decoder = static::createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturn([]);

        static::assertSame([], $this->createCompiler($decoder)->compile([]));
    }

    #[TestDox('throws when a node is not a mapping')]
    public function testNonArrayNodeThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'CT:Content:Text'], 'not-a-node']);
    }

    #[TestDox('throws when a node has no type')]
    public function testMissingTypeThrows(): void
    {
        $this->assertInvalidLayout([['properties' => ['text' => 'x']]]);
    }

    #[TestDox('throws when the type is blank')]
    public function testBlankTypeThrows(): void
    {
        $this->assertInvalidLayout([['component' => '']]);
    }

    #[TestDox('throws when properties is not a mapping')]
    public function testNonArrayPropertiesThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'CT:Content:Text', 'properties' => 'nope']]);
    }

    #[TestDox('throws when slots is not a mapping')]
    public function testNonArraySlotsThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'CT:Grid:Container', 'slots' => 'nope']]);
    }

    #[TestDox('throws when a slot does not map to a list of children')]
    public function testSlotChildrenNotListThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'CT:Grid:Container', 'slots' => ['content' => 'nope']]]);
    }

    /**
     * @param list<mixed> $layout
     */
    private function assertInvalidLayout(array $layout): void
    {
        try {
            $this->createCompiler(static::createStub(DraftLayoutDecoder::class))->compile($layout);
            static::fail('Expected a ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_INVALID_LAYOUT, $e->getErrorCode());
        }
    }

    /**
     * @param array<int, array<string, mixed>> $captured
     */
    private function capturingDecoder(array &$captured): DraftLayoutDecoder
    {
        $decoder = static::createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturnCallback(static function (array $draft) use (&$captured): array {
            $captured = $draft;

            return [];
        });

        return $decoder;
    }

    private function createCompiler(DraftLayoutDecoder $decoder): LayoutPresetPayloadCompiler
    {
        return new LayoutPresetPayloadCompiler(
            $decoder,
            new StoredElementCodec(static::createStub(DataLoaderConfigSerializerProvider::class)),
        );
    }
}
