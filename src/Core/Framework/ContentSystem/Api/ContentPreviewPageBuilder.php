<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Api;

use Contena\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Contena\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Contena\Core\Framework\ContentSystem\ContentPipeline;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\DraftLayoutChecker;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use Contena\Core\Framework\ContentSystem\RenderableLayout;
use Contena\Core\Framework\ContentSystem\RenderingMode;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ContentPreviewPageBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ChannelContextServiceInterface $channelContextService,
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly DraftLayoutDecoder $decoder,
        private readonly DraftLayoutChecker $layoutValidator,
        private readonly ContentPipeline $contentPipeline,
    ) {
    }

    /**
     * @return array{result: RenderResult, channelContext: ChannelContext}
     */
    public function build(ContentPreviewRequest $payload, Context $context): array
    {
        $channelContext = $this->channelContextService->get(
            new ChannelContextServiceParameters(
                channelId: $payload->channelId,
                token: Random::getAlphanumericString(32),
                languageId: $payload->languageId,
                domainId: $payload->domainId,
                originalContext: $context,
                memberId: $payload->memberId,
            )
        );

        $request = new Request($payload->queryParameters);

        $specification = $this->specificationResolver->resolveWithoutLayout(
            $payload->entityType,
            $payload->entityId,
            $request,
            $channelContext,
        );

        $stored = $this->decoder->decode($payload->layout);

        $violations = $this->layoutValidator->check($stored);
        if ($violations->count() > 0) {
            throw ContentSystemException::elementTypesInvalid($violations);
        }

        $renderableLayout = RenderableLayout::create(
            LayoutReference::create(Uuid::randomHex(), 'preview', null),
            $stored,
        );

        $result = $this->contentPipeline->load(
            $renderableLayout,
            $specification,
            new RenderingCacheContext(),
            RenderingMode::FULL,
            // The preview serves the full format, which carries its property values inline.
            false,
            $channelContext,
        );

        return [
            'result' => $result,
            'channelContext' => $channelContext,
        ];
    }
}
