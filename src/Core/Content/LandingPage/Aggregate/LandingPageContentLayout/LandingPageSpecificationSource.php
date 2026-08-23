<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout;

use Contena\Core\Framework\ContentSystem\Adapter\AbstractSpecificationSource;
use Contena\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Contena\Core\Framework\ContentSystem\SpecificationData;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @final
 */
class LandingPageSpecificationSource extends AbstractSpecificationSource
{
    /**
     * @param EntityRepository<LandingPageContentLayoutCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly LandingPageContentLayoutDefinition $definition,
        private readonly EntityLayoutContextFactory $contextFactory,
    ) {
    }

    public function supports(string $path, Request $request, ChannelContext $context): bool
    {
        return $this->contextFactory->supports($path, $this->definition);
    }

    public function resolveLayoutId(string $path, Request $request, ChannelContext $context): string
    {
        return $this->contextFactory->resolveLayoutId($path, $context, $this->repository, $this->definition);
    }

    public function resolveSpecificationData(string $path, Request $request, ChannelContext $context): SpecificationData
    {
        return $this->contextFactory->resolveSpecificationData($path, $request, $context, $this->definition);
    }

    public function resolveTargetElementId(string $path, Request $request, ChannelContext $context): ?string
    {
        return $this->contextFactory->resolveTargetElementId($request);
    }

    /**
     * @return list<string>
     */
    public function resolveCacheTags(string $path, Request $request, ChannelContext $context): array
    {
        return $this->contextFactory->resolveCacheTags($path, $this->definition);
    }

    public function supportsEntityType(string $entityType): bool
    {
        return $this->definition->getContentLayoutEntityType() === $entityType;
    }

    public function resolveSpecificationDataForEntity(string $entityId, Request $request, ChannelContext $context): SpecificationData
    {
        return $this->contextFactory->buildSpecificationData($entityId, $request, $context, $this->definition);
    }

    public function providedRootContext(Context $context): array
    {
        return $this->contextFactory->providedRootContext($this->definition);
    }
}
