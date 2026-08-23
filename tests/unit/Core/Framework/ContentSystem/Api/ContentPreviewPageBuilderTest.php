<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPageBuilder;
use Contena\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Contena\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\ContentPipeline;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\DraftLayoutChecker;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\ContentSystem\RenderableLayout;
use Contena\Core\Framework\ContentSystem\RenderingMode;
use Contena\Core\Framework\ContentSystem\RenderingSpecification;
use Contena\Core\Framework\Context;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(ContentPreviewPageBuilder::class)]
class ContentPreviewPageBuilderTest extends TestCase
{
    #[TestDox('renders the decoded layout through the pipeline in full mode and returns the page and synthesized context')]
    public function testBuildRendersDecodedLayoutThroughThePipeline(): void
    {
        $decodedElement = new ContentElement('e1', 'CT:Content:Heading');
        $specification = $this->specification();
        $channelContext = Generator::generateChannelContext();
        $contentPage = new ContentPage('preview-layout', [$decodedElement], 'preview', null);

        $pipeline = static::createMock(ContentPipeline::class);
        $pipeline->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static fn (RenderableLayout $layout): bool => $layout->elements === [$decodedElement]
                    && $layout->reference->name === 'preview'
                    && $layout->reference->version === null),
                static::identicalTo($specification),
                static::isInstanceOf(RenderingCacheContext::class),
                RenderingMode::FULL,
                static::identicalTo($channelContext),
            )
            ->willReturn($contentPage);

        $builder = new ContentPreviewPageBuilder(
            $this->contextService($channelContext),
            $this->resolverReturning($specification),
            $this->decoderReturning([$decodedElement]),
            $this->checker(new ConstraintViolationList()),
            $pipeline,
        );

        $result = $builder->build($this->request(), Context::createDefaultContext());

        static::assertSame($contentPage, $result['contentPage']);
        static::assertSame($channelContext, $result['channelContext']);
    }

    #[TestDox('throws elementTypesInvalid when the draft check reports a violation')]
    public function testBuildThrowsForUnregisteredComponent(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Component "CT:Unknown:Widget" is not a registered element type.', null, [], null, 'e1', null),
        ]);

        $builder = new ContentPreviewPageBuilder(
            $this->contextService(Generator::generateChannelContext()),
            $this->resolverReturning($this->specification()),
            $this->decoderReturning([new ContentElement('e1', 'CT:Unknown:Widget')]),
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
            layout: [['id' => 'e1', 'component' => 'CT:Content:Heading']],
            entityType: 'blog',
            entityId: 'prod-1',
            channelId: 'sc-1',
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
     * @param list<ContentElement> $elements
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
