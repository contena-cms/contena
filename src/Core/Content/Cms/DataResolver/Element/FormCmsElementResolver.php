<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\DataResolver\Element;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Salutation\AbstractSalutationsSorter;
use Contena\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class FormCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSalutationRoute $salutationRoute,
        private readonly AbstractSalutationsSorter $salutationSorter
    ) {
    }

    public function getType(): string
    {
        return 'form';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $context = $resolverContext->getSalesChannelContext();

        $salutations = $this->salutationRoute->load(new Request(), $context, new Criteria())->getSalutations();
        $slot->setData($this->salutationSorter->sort($salutations));
    }
}
