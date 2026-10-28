<?php declare(strict_types=1);

namespace Contena\Storefront\ContentSystem\HeaderContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Contena\Core\Framework\ContentSystem\ContentSection;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\ContentSystem\SpecificationData;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Uses domain-aware resolution instead of entity-based path matching.
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class HeaderSpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<HeaderContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly DomainAwareLayoutResolver $resolver,
        private readonly EntityRepository $repository,
    ) {
    }

    public function supports(string $path, Request $request, SalesChannelContext $context): bool
    {
        return true;
    }

    public function resolveLayoutId(string $path, Request $request, SalesChannelContext $context): string
    {
        return $this->resolveAssignment($context)->getContentLayoutId();
    }

    public function resolveSpecificationData(string $path, Request $request, SalesChannelContext $context): SpecificationData
    {
        $scalarParameters = array_filter($request->query->all(), '\is_scalar');

        return new SpecificationData(
            dataRequirements: [],
            placeholderValues: PlaceholderValues::from($scalarParameters),
        );
    }

    /**
     * @codeCoverageIgnore
     */
    public function resolveTargetElementId(string $path, Request $request, SalesChannelContext $context): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, Request $request, SalesChannelContext $context): array
    {
        $layoutId = $this->resolveAssignment($context)->getContentLayoutId();

        return [ContentSection::HEADER->buildLayoutTag($layoutId)];
    }

    private function resolveAssignment(SalesChannelContext $context): AbstractContentLayoutAssignmentEntity
    {
        $assignment = $this->resolver->resolve($context, $this->repository);

        if ($assignment === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                'header',
                '',
                $context->getSalesChannel()->getId()
            );
        }

        return $assignment;
    }
}
