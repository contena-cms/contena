<?php declare(strict_types=1);

namespace Contena\Frontend\ContentSystem\FooterContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Contena\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Contena\Core\Framework\ContentSystem\ContentSection;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\PlaceholderValues;
use Contena\Core\Framework\ContentSystem\SpecificationData;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Uses domain-aware resolution instead of entity-based path matching.
 *
 * @internal
 *
 * @final
 */
class FooterSpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<FooterContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly DomainAwareLayoutResolver $resolver,
        private readonly EntityRepository $repository,
    ) {
    }

    public function supports(string $path, Request $request, ChannelContext $context): bool
    {
        return true;
    }

    public function resolveLayoutId(string $path, Request $request, ChannelContext $context): string
    {
        return $this->resolveAssignment($context)->getContentLayoutId();
    }

    public function resolveSpecificationData(string $path, Request $request, ChannelContext $context): SpecificationData
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
    public function resolveTargetElementId(string $path, Request $request, ChannelContext $context): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, Request $request, ChannelContext $context): array
    {
        $layoutId = $this->resolveAssignment($context)->getContentLayoutId();

        return [ContentSection::FOOTER->buildLayoutTag($layoutId)];
    }

    private function resolveAssignment(ChannelContext $context): AbstractContentLayoutAssignmentEntity
    {
        $assignment = $this->resolver->resolve($context, $this->repository);

        if ($assignment === null) {
            throw ContentSystemException::layoutAssignmentNotFound(
                'footer',
                '',
                $context->getChannel()->getId()
            );
        }

        return $assignment;
    }
}
