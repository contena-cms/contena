<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutAttachRequest;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutDuplicateRequest;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutInsertRequest;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutMoveRequest;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutMutationController;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutRemoveRequest;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutReplaceRequest;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutUnwrapRequest;
use Contena\Core\Framework\ContentSystem\Api\ContentLayoutWrapElementsRequest;
use Contena\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Contena\Core\Framework\ContentSystem\Mutation\MutationResult;
use Contena\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Contena\Core\Framework\ContentSystem\Mutation\PersistedLayoutMutator;
use Contena\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ContentLayoutMutationController::class)]
class ContentLayoutMutationControllerTest extends TestCase
{
    #[TestDox('serializes the persisted mutation result into the layout, resolutions, diagnostics and affected ids')]
    public function testInsertSerializesMutationResult(): void
    {
        $result = new MutationResult([new ContentElement('el-1', 'CT:Card')], ['el-1' => []], new DiagnosticsReport([]), ['el-1']);
        $controller = $this->controller($this->mutatorReturning($result));

        $response = $controller->insert('layout-1', new ContentLayoutInsertRequest('CT:Card', null), Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->decode($response);
        static::assertSame('el-1', $body['layout'][0]['id']);
        static::assertSame(['el-1'], $body['affectedElementIds']);
        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertArrayHasKey('el-1', $body['resolutions']);
    }

    #[TestDox('passes the path layout id and expected version token through to the mutator')]
    public function testPassesLayoutIdAndExpectedVersionToMutator(): void
    {
        $capturedId = null;
        $capturedVersion = false;
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturnCallback(
            function (string $layoutId, ?string $expectedVersion) use (&$capturedId, &$capturedVersion): MutationResult {
                $capturedId = $layoutId;
                $capturedVersion = $expectedVersion;

                return new MutationResult([], [], new DiagnosticsReport([]), []);
            }
        );

        $this->controller($mutator)->remove('layout-42', new ContentLayoutRemoveRequest('el', '2026-06-22T10:00:00.000+00:00'), Context::createDefaultContext());

        static::assertSame('layout-42', $capturedId);
        static::assertSame('2026-06-22T10:00:00.000+00:00', $capturedVersion);
    }

    /**
     * @param \Closure(ContentLayoutMutationController): Response $invoke
     * @param class-string<LayoutMutation> $expectedOp
     */
    #[DataProvider('dispatchesExpectedOpProvider')]
    #[TestDox('dispatches each route to the matching mutation op')]
    public function testRouteDispatchesExpectedOp(\Closure $invoke, string $expectedOp): void
    {
        $captured = null;
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturnCallback(
            function (string $layoutId, ?string $expectedVersion, LayoutMutation $mutation) use (&$captured): MutationResult {
                $captured = $mutation;

                return new MutationResult([], [], new DiagnosticsReport([]), []);
            }
        );

        $invoke($this->controller($mutator));

        static::assertInstanceOf($expectedOp, $captured);
    }

    /**
     * @return iterable<string, array{\Closure(ContentLayoutMutationController): Response, class-string<LayoutMutation>}>
     */
    public static function dispatchesExpectedOpProvider(): iterable
    {
        $context = Context::createDefaultContext();

        yield 'insert' => [static fn (ContentLayoutMutationController $c): Response => $c->insert('l', new ContentLayoutInsertRequest('CT:Card', null), $context), InsertElement::class];
        yield 'remove' => [static fn (ContentLayoutMutationController $c): Response => $c->remove('l', new ContentLayoutRemoveRequest('el', null), $context), RemoveElement::class];
        yield 'move' => [static fn (ContentLayoutMutationController $c): Response => $c->move('l', new ContentLayoutMoveRequest('el', null), $context), MoveElement::class];
        yield 'replace' => [static fn (ContentLayoutMutationController $c): Response => $c->replace('l', new ContentLayoutReplaceRequest('el', 'CT:New', null), $context), ReplaceElement::class];
        yield 'duplicate' => [static fn (ContentLayoutMutationController $c): Response => $c->duplicate('l', new ContentLayoutDuplicateRequest('el', null), $context), DuplicateElement::class];
        yield 'wrap' => [static fn (ContentLayoutMutationController $c): Response => $c->wrap('l', new ContentLayoutWrapElementsRequest(['a'], 'CT:Container', null), $context), WrapElements::class];
        yield 'unwrap' => [static fn (ContentLayoutMutationController $c): Response => $c->unwrap('l', new ContentLayoutUnwrapRequest('el', null), $context), UnwrapElement::class];
        yield 'attach' => [static fn (ContentLayoutMutationController $c): Response => $c->attach('l', new ContentLayoutAttachRequest(['id' => 'incoming', 'component' => 'CT:Card'], null), $context), AttachElement::class];
    }

    /**
     * @param \Closure(array<string, mixed>): void $assert
     */
    #[DataProvider('serializesOptionalReplaceFieldsProvider')]
    #[TestDox('serializes the populated optional replace fields in the persisted response')]
    public function testReplaceSerializesOptionalFields(MutationResult $result, \Closure $assert): void
    {
        $controller = $this->controller($this->mutatorReturning($result));

        $response = $controller->replace('layout-1', new ContentLayoutReplaceRequest('el', 'CT:New', null), Context::createDefaultContext());

        $assert($this->decode($response));
    }

    /**
     * @return iterable<string, array{MutationResult, \Closure(array<string, mixed>): void}>
     */
    public static function serializesOptionalReplaceFieldsProvider(): iterable
    {
        yield 'orphaned subtrees surface for re-attachment' => [
            new MutationResult([new ContentElement('el', 'CT:New')], [], new DiagnosticsReport([]), ['el'], [new ContentElement('orphan', 'CT:Block')]),
            static function (array $body): void {
                static::assertSame('orphan', $body['orphaned'][0]['id']);
            },
        ];

        yield 'dropped wiring keys are reported' => [
            new MutationResult([new ContentElement('el', 'CT:New')], [], new DiagnosticsReport([]), ['el'], [], ['legacy']),
            static function (array $body): void {
                static::assertSame(['legacy'], $body['droppedWiring']);
            },
        ];

        yield 'dropped property values are reported' => [
            new MutationResult([new ContentElement('el', 'CT:New')], [], new DiagnosticsReport([]), ['el'], [], [], ['headline' => 'Old headline']),
            static function (array $body): void {
                static::assertSame('Old headline', $body['droppedProperties']['headline']);
            },
        ];
    }

    #[TestDox('encodes an empty resolutions map as a JSON object, not an array')]
    public function testEmptyResolutionsEncodeAsJsonObject(): void
    {
        $controller = $this->controller($this->mutatorReturning(new MutationResult([], [], new DiagnosticsReport([]), [])));

        $response = $controller->remove('layout-1', new ContentLayoutRemoveRequest('el', null), Context::createDefaultContext());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('"resolutions":{}', $content);
    }

    #[TestDox('propagates contentLayoutNotFound from the mutator for an unknown layout id')]
    public function testMutateThrowsWhenLayoutNotFound(): void
    {
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willThrowException(ContentSystemException::contentLayoutNotFound('layout-404'));

        try {
            $this->controller($mutator)->remove('layout-404', new ContentLayoutRemoveRequest('el', null), Context::createDefaultContext());
            static::fail('Expected a ' . ContentSystemException::CONTENT_LAYOUT_NOT_FOUND . ' exception, but none was thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::CONTENT_LAYOUT_NOT_FOUND, $exception->getErrorCode());
            static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        }
    }

    #[TestDox('propagates layoutVersionConflict from the mutator for a stale expected version token')]
    public function testMutateThrowsOnStaleVersionToken(): void
    {
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willThrowException(ContentSystemException::layoutVersionConflict('layout-1'));

        try {
            $this->controller($mutator)->remove('layout-1', new ContentLayoutRemoveRequest('el', '2020-01-01T00:00:00.000+00:00'), Context::createDefaultContext());
            static::fail('Expected a ' . ContentSystemException::LAYOUT_VERSION_CONFLICT . ' exception, but none was thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::LAYOUT_VERSION_CONFLICT, $exception->getErrorCode());
            static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        }
    }

    #[TestDox('rejects a malformed attach element with invalidLayoutStructure before the mutator is invoked')]
    public function testAttachThrowsWhenElementStructureIsInvalid(): void
    {
        $mutator = static::createStub(PersistedLayoutMutator::class);

        try {
            $this->controller($mutator)->attach('layout-1', new ContentLayoutAttachRequest(['component' => 'CT:Card'], null), Context::createDefaultContext());
            static::fail('Expected a ' . ContentSystemException::INVALID_LAYOUT_STRUCTURE . ' exception, but none was thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_LAYOUT_STRUCTURE, $exception->getErrorCode());
            static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }

    private function controller(PersistedLayoutMutator $mutator): ContentLayoutMutationController
    {
        return new ContentLayoutMutationController(
            $mutator,
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $this->elementSerializer(),
            $this->decoder(),
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            // BindingApplicator is final: a real instance over a stubbed serializer provider.
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class)),
        );
    }

    private function decoder(): DraftLayoutDecoder
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('decodeElement')->willReturnCallback(
            static fn (array $raw): ContentElement => new ContentElement(
                \is_string($raw['id'] ?? null) ? $raw['id'] : 'incoming',
                \is_string($raw['component'] ?? null) ? $raw['component'] : 'CT:Card',
            ),
        );

        return new DraftLayoutDecoder($serializer);
    }

    private function mutatorReturning(MutationResult $result): PersistedLayoutMutator
    {
        $mutator = static::createStub(PersistedLayoutMutator::class);
        $mutator->method('mutate')->willReturn($result);

        return $mutator;
    }

    private function elementSerializer(): ContentElementFieldSerializer
    {
        $serializer = static::createStub(ContentElementFieldSerializer::class);
        $serializer->method('serializeContentElement')->willReturnCallback(
            static fn (ContentElement $element): array => ['id' => $element->getId(), 'component' => $element->getComponent(), 'properties' => []],
        );

        return $serializer;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        $content = $response->getContent();
        static::assertIsString($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
