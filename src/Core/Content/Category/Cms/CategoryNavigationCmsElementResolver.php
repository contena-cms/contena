<?php

declare(strict_types=1);

namespace Contena\Core\Content\Category\Cms;

use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
class CategoryNavigationCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly NavigationLoaderInterface $navigationLoader,
    ) {
    }

    /**
     * @codeCoverageIgnore
     */
    public function getType(): string
    {
        return 'category-navigation';
    }

    /**
     * @codeCoverageIgnore
     */
    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $salesChannelContext = $resolverContext->getSalesChannelContext();
        $salesChannel = $salesChannelContext->getSalesChannel();

        $rootNavigationId = $salesChannel->getNavigationCategoryId();
        $request = $resolverContext->getRequest();
        $navigationId = (string) ($request->attributes->get('navigationId') ?? RequestParamHelper::get($request, 'navigationId', $rootNavigationId));

        $tree = $this->navigationLoader->load(
            $navigationId,
            $salesChannelContext,
            $rootNavigationId,
            $salesChannel->getNavigationCategoryDepth()
        );

        $slot->setData($tree);
    }
}
