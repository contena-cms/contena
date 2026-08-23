<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Contena\Core\Framework\ContentSystem\Api\AttachElementRequest;
use Contena\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Contena\Core\Framework\ContentSystem\Api\DuplicateElementRequest;
use Contena\Core\Framework\ContentSystem\Api\InsertElementRequest;
use Contena\Core\Framework\ContentSystem\Api\LayoutMutationController;
use Contena\Core\Framework\ContentSystem\Api\MoveElementRequest;
use Contena\Core\Framework\ContentSystem\Api\RemoveElementRequest;
use Contena\Core\Framework\ContentSystem\Api\ReplaceElementRequest;
use Contena\Core\Framework\ContentSystem\Api\UnwrapElementRequest;
use Contena\Core\Framework\ContentSystem\Api\WrapElementsRequest;
use Contena\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Diagnostics\DiagnosticsReport;
use Contena\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\DistributionStrategy;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContentElementFieldSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Mutation\LayoutMutation;
use Contena\Core\Framework\ContentSystem\Mutation\MutationPipeline;
use Contena\Core\Framework\ContentSystem\Mutation\MutationResult;
use Contena\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\DuplicateElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\InsertElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\MoveElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\RemoveElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\UnwrapElement;
use Contena\Core\Framework\ContentSystem\Mutation\Op\WrapElements;
use Contena\Core\Framework\ContentSystem\Resolution\ProvidedContext;
use Contena\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(LayoutMutationController::class)]
class LayoutMutationControllerTest extends TestCase
{
    #[TestDox('serializes the mutation result into the layout, resolutions, diagnostics and affected ids')]
    public function testInsertSerializesMutationResult(): void
    {
        $result = new MutationResult([new ContentElement('el-1', 'CT:Card')], ['el-1' => []], new DiagnosticsReport([]), ['el-1']);
        $controller = $this->controller($this->pipelineReturning($result));

        $response = $controller->insert(new InsertElementRequest('CT:Card'), Context::createDefaultContext());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $body = $this->decode($response);
        static::assertSame('el-1', $body['layout'][0]['id']);
        static::assertSame(['el-1'], $body['affectedElementIds']);
        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertArrayHasKey('el-1', $body['resolutions']);
    }

    /**
     * @param \Closure(LayoutMutationController): Response $invoke
     * @param class-string<LayoutMutation> $expectedOp
     */
    #[DataProvider('dispatchesExpectedOpProvider')]
    #[TestDox('dispatches each route to the matching mutation op')]
    public function testRouteDispatchesExpectedOp(\Closure $invoke, string $expectedOp): void
    {
        $captured = null;
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturnCallback(function (LayoutMutation $mutation) use (&$captured): MutationResult {
            $captured = $mutation;

            return new MutationResult([], [], new DiagnosticsReport([]), []);
        });

        $invoke($this->controller($pipeline));

        static::assertInstanceOf($expectedOp, $captured);
    }

    /**
     * @param \Closure(mixed): mixed $accessor
     */
    #[DataProvider('replaceOptionalFieldsProvider')]
    #[TestDox('serializes the populated optional replace fields in the response')]
    public function testReplaceSerializesOptionalFields(MutationResult $result, string $field, \Closure $accessor, mixed $expected): void
    {
        $controller = $this->controller($this->pipelineReturning($result));

        $response = $controller->replace(new ReplaceElementRequest('el', 'CT:New'), Context::createDefaultContext());

        static::assertSame($expected, $accessor($this->decode($response)[$field]));
    }

    #[TestDox('threads the root source context resolved from the registry into the mutation pipeline')]
    public function testResolvesRootSource(): void
    {
        $rootContext = [new ProvidedContext(
            contextKey: 'blog',
            fqcn: ContentElement::class,
            contextType: ContextType::Single,
            providerElementId: null,
            distribution: DistributionStrategy::Broadcast,
        )];

        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willReturn($rootContext);

        $threadedRootContext = false;
        $controller = $this->controller($this->capturingPipeline($threadedRootContext), $registry);

        $controller->insert(new InsertElementRequest('CT:Card', rootSource: 'blog'), Context::createDefaultContext());

        static::assertSame($rootContext, $threadedRootContext);
    }

    #[TestDox('threads a null context into the pipeline when the registry resolves no bound source')]
    public function testWithoutRootSourceThreadsNullContext(): void
    {
        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willReturn(null);

        $threadedRootContext = 'unset';
        $controller = $this->controller($this->capturingPipeline($threadedRootContext), $registry);

        $controller->insert(new InsertElementRequest('CT:Card'), Context::createDefaultContext());

        static::assertNull($threadedRootContext);
    }

    #[TestDox('encodes an empty resolutions map as a JSON object, not an array')]
    public function testEmptyResolutionsEncodeAsJsonObject(): void
    {
        $result = new MutationResult([], [], new DiagnosticsReport([]), []);
        $controller = $this->controller($this->pipelineReturning($result));

        $response = $controller->remove(new RemoveElementRequest('el'), Context::createDefaultContext());

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('"resolutions":{}', $content);
    }

    #[TestDox('propagates the registry unknownRootSource exception instead of reaching the pipeline')]
    public function testRejectsUnknownRootSource(): void
    {
        $registry = static::createStub(RootSourceRegistry::class);
        $registry->method('resolveGated')->willThrowException(
            ContentSystemException::unknownRootSource('definitely-not-a-root-source')
        );

        $controller = $this->controller(rootSourceRegistry: $registry);

        try {
            $controller->insert(new InsertElementRequest('CT:Card', rootSource: 'definitely-not-a-root-source'), Context::createDefaultContext());
            static::fail('Expected a ContentSystemException for the unknown root source.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::UNKNOWN_ROOT_SOURCE, $exception->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{\Closure(LayoutMutationController): Response, class-string<LayoutMutation>}>
     */
    public static function dispatchesExpectedOpProvider(): iterable
    {
        $context = Context::createDefaultContext();

        yield 'insert' => [static fn (LayoutMutationController $c): Response => $c->insert(new InsertElementRequest('CT:Card'), $context), InsertElement::class];
        yield 'remove' => [static fn (LayoutMutationController $c): Response => $c->remove(new RemoveElementRequest('el'), $context), RemoveElement::class];
        yield 'move' => [static fn (LayoutMutationController $c): Response => $c->move(new MoveElementRequest('el'), $context), MoveElement::class];
        yield 'replace' => [static fn (LayoutMutationController $c): Response => $c->replace(new ReplaceElementRequest('el', 'CT:New'), $context), ReplaceElement::class];
        yield 'duplicate' => [static fn (LayoutMutationController $c): Response => $c->duplicate(new DuplicateElementRequest('el'), $context), DuplicateElement::class];
        yield 'wrap' => [static fn (LayoutMutationController $c): Response => $c->wrap(new WrapElementsRequest(['a'], 'CT:Container'), $context), WrapElements::class];
        yield 'unwrap' => [static fn (LayoutMutationController $c): Response => $c->unwrap(new UnwrapElementRequest('el'), $context), UnwrapElement::class];
        yield 'attach' => [static fn (LayoutMutationController $c): Response => $c->attach(new AttachElementRequest(['id' => 'incoming', 'component' => 'CT:Card']), $context), AttachElement::class];
    }

    /**
     * @return iterable<string, array{MutationResult, string, \Closure(mixed): mixed, mixed}>
     */
    public static function replaceOptionalFieldsProvider(): iterable
    {
        yield 'orphaned subtrees surface for re-attachment' => [
            new MutationResult([new ContentElement('el', 'CT:New')], [], new DiagnosticsReport([]), ['el'], [new ContentElement('orphan', 'CT:Block')]),
            'orphaned',
            static fn (mixed $value): mixed => $value[0]['id'],
            'orphan',
        ];

        yield 'dropped wiring keys are reported' => [
            new MutationResult([new ContentElement('el', 'CT:New')], [], new DiagnosticsReport([]), ['el'], [], ['legacy']),
            'droppedWiring',
            static fn (mixed $value): mixed => $value,
            ['legacy'],
        ];

        yield 'dropped property values are reported' => [
            new MutationResult([new ContentElement('el', 'CT:New')], [], new DiagnosticsReport([]), ['el'], [], [], ['headline' => 'Old headline']),
            'droppedProperties',
            static fn (mixed $value): mixed => $value['headline'],
            'Old headline',
        ];
    }

    private function controller(
        ?MutationPipeline $pipeline = null,
        ?RootSourceRegistry $rootSourceRegistry = null,
    ): LayoutMutationController {
        return new LayoutMutationController(
            $this->decoder(),
            $pipeline ?? $this->pipelineReturning(new MutationResult([], [], new DiagnosticsReport([]), [])),
            static::createStub(AbstractContentSystemElementTypeRegistry::class),
            $rootSourceRegistry ?? static::createStub(RootSourceRegistry::class),
            $this->elementSerializer(),
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            // BindingApplicator is final: a real instance over a stubbed serializer provider.
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class)),
        );
    }

    /**
     * Builds a pipeline stub that captures the root context threaded into run() so a test can assert what the
     * controller resolved and passed through.
     */
    private function capturingPipeline(mixed &$captured): MutationPipeline
    {
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturnCallback(
            function (LayoutMutation $mutation, array $tree, ?array $analyzedRootContext) use (&$captured): MutationResult {
                $captured = $analyzedRootContext;

                return new MutationResult([], [], new DiagnosticsReport([]), []);
            }
        );

        return $pipeline;
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

    private function pipelineReturning(MutationResult $result): MutationPipeline
    {
        $pipeline = static::createStub(MutationPipeline::class);
        $pipeline->method('run')->willReturn($result);

        return $pipeline;
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
