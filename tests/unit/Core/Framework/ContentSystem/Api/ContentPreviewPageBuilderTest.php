<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Api;

use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Contena\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\ContentPipeline;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\DraftLayoutChecker;
use Contena\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\ContentSystem\RenderableLayout;
use Contena\Core\Framework\ContentSystem\RenderingMode;
use Contena\Core\Framework\ContentSystem\RenderingSpecification;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\Test\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(ContentPreviewPageBuilder::class)]
class ContentPreviewPageBuilderTest extends TestCase
{
    #[TestDox('checks the decoded stored tree and hands the same tree to the pipeline in full mode, returning the render result and synthesized context')]
    public function testBuildChecksStoredTreeAndRendersItThroughThePipeline(): void
    {
        $specification = $this->specification();
        $channelContext = Generator::generateChannelContext();
        $renderResult = new RenderResult([], LayoutReference::create('preview-layout', 'preview', null), null);
        $stored = [new StoredElement('e1', 'Ct:Content:Heading')];

        // Both halves read the decoded stored tree: the check takes it directly, and the pipeline takes it
        // wrapped in a preview-labelled RenderableLayout, lowering it itself once its stored steps have run.
        $checker = static::createMock(DraftLayoutChecker::class);
        $checker->expects($this->once())
            ->method('check')
            ->with(static::identicalTo($stored))
            ->willReturn(new ConstraintViolationList());

        $pipeline = static::createMock(ContentPipeline::class);
        $pipeline->expects($this->atLeastOnce())
            ->method('load')
            ->with(
                static::callback(static function (RenderableLayout $layout) use ($stored): bool {
                    static::assertSame($stored, $layout->elements);
                    static::assertSame('preview', $layout->reference->name);
                    static::assertNull($layout->reference->version);

                    return true;
                }),
                static::identicalTo($specification),
                static::isInstanceOf(RenderingCacheContext::class),
                RenderingMode::FULL,
                false,
                static::identicalTo($channelContext),
            )
            ->willReturn($renderResult);

        $builder = new ContentPreviewPageBuilder(
            $this->contextService($channelContext),
            $this->resolverReturning($specification),
            $this->decoderReturning($stored),
            $checker,
            $pipeline,
        );

        $result = $builder->build($this->request(), Context::createDefaultContext());

        static::assertSame($renderResult, $result['result']);
        static::assertSame($channelContext, $result['channelContext']);
    }

    #[TestDox('throws elementTypesInvalid when the draft check reports a violation')]
    public function testBuildThrowsForUnregisteredComponent(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Component "Ct:Unknown:Widget" is not a registered element type.', null, [], null, 'e1', null),
        ]);

        $builder = new ContentPreviewPageBuilder(
            $this->contextService(Generator::generateChannelContext()),
            $this->resolverReturning($this->specification()),
            $this->decoderReturning([new StoredElement('e1', 'Ct:Unknown:Widget')]),
            $this->checker($violations),
            static::createStub(ContentPipeline::class),
        );

        $this->expectExceptionObject(ContentSystemException::elementTypesInvalid($violations));

        $builder->build($this->request(), Context::createDefaultContext());
    }

    #[TestDox('propagates unknownEntityType when the resolver cannot match the entity type')]
    public function testBuildPropagatesUnknownEntityType(): void
    {
        $resolver = static::createStub(RenderingSpecificationResolver::class);
        $resolver->method('resolveWithoutLayout')
            ->willThrowException(ContentSystemException::unknownEntityType('mystery'));

        $builder = new ContentPreviewPageBuilder(
            $this->contextService(Generator::generateChannelContext()),
            $resolver,
            static::createStub(DraftLayoutDecoder::class),
            static::createStub(DraftLayoutChecker::class),
            static::createStub(ContentPipeline::class),
        );

        $this->expectExceptionObject(ContentSystemException::unknownEntityType('mystery'));

        $builder->build($this->request(), Context::createDefaultContext());
    }

    #[TestDox('propagates a channel context synthesis failure')]
    public function testBuildPropagatesContextSynthesisFailure(): void
    {
        $failure = new \RuntimeException('invalid channel');

        $contextService = static::createStub(ChannelContextServiceInterface::class);
        $contextService->method('get')->willThrowException($failure);

        $builder = new ContentPreviewPageBuilder(
            $contextService,
            static::createStub(RenderingSpecificationResolver::class),
            static::createStub(DraftLayoutDecoder::class),
            static::createStub(DraftLayoutChecker::class),
            static::createStub(ContentPipeline::class),
        );

        $this->expectExceptionObject($failure);

        $builder->build($this->request(), Context::createDefaultContext());
    }

    private function request(): ContentPreviewRequest
    {
        return new ContentPreviewRequest(
            layout: [['id' => 'e1', 'component' => 'Ct:Content:Heading']],
            entityType: 'blog',
            entityId: 'blog-1',
            channelId: 'channel-1',
        );
    }

    private function specification(): RenderingSpecification
    {
        return new RenderingSpecification([], PlaceholderValues::from([]), new Request());
    }

    private function contextService(ChannelContext $context): ChannelContextServiceInterface
    {
        $service = static::createStub(ChannelContextServiceInterface::class);
        $service->method('get')->willReturn($context);

        return $service;
    }

    private function resolverReturning(RenderingSpecification $specification): RenderingSpecificationResolver
    {
        $resolver = static::createStub(RenderingSpecificationResolver::class);
        $resolver->method('resolveWithoutLayout')->willReturn($specification);

        return $resolver;
    }

    /**
     * @param list<StoredElement> $elements
     */
    private function decoderReturning(array $elements): DraftLayoutDecoder
    {
        $decoder = static::createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturn($elements);

        return $decoder;
    }

    private function checker(ConstraintViolationList $violations): DraftLayoutChecker
    {
        $checker = static::createStub(DraftLayoutChecker::class);
        $checker->method('check')->willReturn($violations);

        return $checker;
    }
}
