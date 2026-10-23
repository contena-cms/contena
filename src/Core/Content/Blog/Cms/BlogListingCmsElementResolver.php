<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Content\Cms\Channel\Struct\BlogListingStruct;
use Contena\Core\Content\Blog\Channel\Listing\AbstractBlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\Filter\ManufacturerListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\PriceListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\PropertyListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\RatingListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Listing\Filter\ShippingFreeListingFilterHandler;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class BlogListingCmsElementResolver extends AbstractCmsElementResolver
{
    private const FILTER_REQUEST_PARAMS = [
        ManufacturerListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        RatingListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        ShippingFreeListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        PriceListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
        PropertyListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM,
    ];

    /**
     * @internal
     *
     * @param EntityRepository<BlogSortingCollection> $sortingRepository
     */
    public function __construct(
        private readonly AbstractBlogListingRoute $listingRoute,
        private readonly EntityRepository $sortingRepository
    ) {
    }

    public function getType(): string
    {
        return 'blog-listing';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new BlogListingStruct();
        $slot->setData($data);

        $request = $resolverContext->getRequest();
        $context = $resolverContext->getChannelContext();

        $this->restrictFilters($slot, $request);

        if ($this->isCustomSorting($slot)) {
            $this->restrictSortings($request, $slot);
            $this->addDefaultSorting($request, $slot, $context);
        }

        $navigationId = $this->getNavigationId($request, $context);

        $criteria = new Criteria();
        $criteria->setTitle('cms::blog-listing');

        $listing = $this->listingRoute
            ->load($navigationId, $request, $context, $criteria)
            ->getResult();

        $data->setListing($listing);
    }

    private function getNavigationId(Request $request, ChannelContext $channelContext): string
    {
        return (string) (
            $request->attributes->get('navigationId')
            ?? RequestParamHelper::get($request, 'navigationId', $channelContext->getChannel()->getNavigationCategoryId())
        );
    }

    private function isCustomSorting(CmsSlotEntity $slot): bool
    {
        $config = $slot->getTranslation('config');

        if ($config && isset($config['useCustomSorting']) && isset($config['useCustomSorting']['value'])) {
            return $config['useCustomSorting']['value'];
        }

        return false;
    }

    private function addDefaultSorting(Request $request, CmsSlotEntity $slot, ChannelContext $context): void
    {
        if (RequestParamHelper::get($request, 'order')) {
            return;
        }

        $config = $slot->getTranslation('config');

        if ($config && isset($config['defaultSorting']) && isset($config['defaultSorting']['value']) && $config['defaultSorting']['value']) {
            $defaultSortingValue = $config['defaultSorting']['value'];
            $criteria = new Criteria([$defaultSortingValue]);

            $request->request->set('order', $this->sortingRepository->search($criteria, $context->getContext())->getEntities()->first()?->get('key'));

            return;
        }

        // if we have no specific order given at this point, set the order to the highest priority available sorting
        $availableSortings = RequestParamHelper::get($request, 'availableSortings');
        if ($availableSortings) {
            arsort($availableSortings);
            $sortingId = array_key_first($availableSortings);
            if (!\is_string($sortingId)) {
                return;
            }

            $criteria = new Criteria([$sortingId]);

            $request->request->set('order', $this->sortingRepository->search($criteria, $context->getContext())->getEntities()->first()?->get('key'));
        }
    }

    private function restrictSortings(Request $request, CmsSlotEntity $slot): void
    {
        $config = $slot->getTranslation('config');

        if (!$config || !isset($config['availableSortings']) || !isset($config['availableSortings']['value'])) {
            return;
        }

        $request->request->set('availableSortings', $config['availableSortings']['value']);
    }

    private function restrictFilters(CmsSlotEntity $slot, Request $request): void
    {
        $config = $slot->get('config');

        $enabledFilters = $config['filters']['value'] ?? null;

        $enabledFilters = \is_string($enabledFilters) ? explode(',', $enabledFilters) : self::FILTER_REQUEST_PARAMS;

        $propertyWhitelist = $config['propertyWhitelist']['value'] ?? null ?: null;

        // When the property filters are restricted, they are not in the enabledFilters array
        if (\in_array(PropertyListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM, $enabledFilters, true)
            || !\is_array($propertyWhitelist)) {
            $propertyWhitelist = null;
        }

        $request->request->set(PropertyListingFilterHandler::PROPERTY_GROUP_IDS_REQUEST_PARAM, $propertyWhitelist);

        foreach (self::FILTER_REQUEST_PARAMS as $filterParam) {
            $request->request->set($filterParam, \in_array($filterParam, $enabledFilters, true));
        }
    }
}
