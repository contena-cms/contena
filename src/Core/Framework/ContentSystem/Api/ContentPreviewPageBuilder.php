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
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
class ContentPreviewPageBuilder
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $salesChannelContextService,
        private readonly RenderingSpecificationResolver $specificationResolver,
        private readonly DraftLayoutDecoder $decoder,
        private readonly DraftLayoutChecker $layoutValidator,
        private readonly ContentPipeline $contentPipeline,
    ) {
    }

    /**
     * @return array{result: RenderResult, salesChannelContext: SalesChannelContext}
     */
    public function build(ContentPreviewRequest $payload, Context $context): array
    {
        $salesChannelContext = $this->salesChannelContextService->get(
            new SalesChannelContextServiceParameters(
                $payload->salesChannelId,
                Random::getAlphanumericString(32),
                $payload->languageId,
                $payload->currencyId,
                $payload->domainId,
                $context,
                $payload->customerId,
            )
        );

        $request = new Request($payload->queryParameters);

        $specification = $this->specificationResolver->resolveWithoutLayout(
            $payload->entityType,
            $payload->entityId,
            $request,
            $salesChannelContext,
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
            $salesChannelContext,
        );

        return [
            'result' => $result,
            'salesChannelContext' => $salesChannelContext,
        ];
    }
}
