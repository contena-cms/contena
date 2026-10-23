<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing\Processor;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingEntity;
use Contena\Core\Content\Blog\Events\BlogListingCollectSortingEvent;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

class SortingListingProcessor extends AbstractListingProcessor
{
    /**
     * Transports the collected sortings from prepare() to process(). Not an extension point:
     * use BlogListingCollectSortingEvent to add or remove sortings, and
     * BlogListingResult::getAvailableSortings() to read them.
     *
     * @internal
     */
    final public const SORTINGS_EXTENSION = 'sortings';

    /**
     * @param EntityRepository<BlogSortingCollection> $sortingRepository
     *
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $sortingRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, ChannelContext $context): void
    {
        if (!RequestParamHelper::get($request, 'order')) {
            $key = RequestParamHelper::get($request, 'search') ? 'core.listing.defaultSearchResultSorting' : 'core.listing.defaultSorting';
            $request->request->set('order', $this->getDefaultSortingKey($key, $context));
        }

        /** @var BlogSortingCollection $sortings */
        $sortings = $criteria->getExtension(self::SORTINGS_EXTENSION) ?? new BlogSortingCollection();
        $sortings->merge($this->getAvailableSortings($request, $context->getContext()));

        $this->dispatcher->dispatch(new BlogListingCollectSortingEvent($request, $sortings, $context));

        $currentSorting = $this->getCurrentSorting($sortings, $request, $context->getChannelId());

        if ($currentSorting !== null) {
            $fallbackSorting = null;
            if ($this->hasQueriesOrTerm($criteria)) {
                $fallbackSorting = new FieldSorting(Criteria::SCORE_FIELD, FieldSorting::DESCENDING);
            }

            $criteria->addSorting(
                ...$currentSorting->createDalSorting($fallbackSorting)
            );
        }

        $criteria->addExtension(self::SORTINGS_EXTENSION, $sortings);
    }

    public function process(Request $request, BlogListingResult $result, ChannelContext $context): void
    {
        /** @var BlogSortingCollection $sortings */
        $sortings = $result->getCriteria()->getExtension(self::SORTINGS_EXTENSION);
        $currentSorting = $this->getCurrentSorting($sortings, $request, $context->getChannelId());

        if ($currentSorting !== null) {
            $result->setSorting($currentSorting->getKey());
        }

        $result->setAvailableSortings($sortings);
    }

    private function hasQueriesOrTerm(Criteria $criteria): bool
    {
        return $criteria->getQueries() !== [] || $criteria->getTerm();
    }

    private function getCurrentSorting(BlogSortingCollection $sortings, Request $request, string $channelId): ?BlogSortingEntity
    {
        $key = RequestParamHelper::get($request, 'order');

        if (!\is_string($key)) {
            throw BlogException::sortingNotFoundException('');
        }

        $sorting = $sortings->getByKey($key);
        if ($sorting !== null) {
            return $sorting;
        }

        return $sortings->get($this->systemConfigService->getString('core.listing.defaultSorting', $channelId));
    }

    private function getAvailableSortings(Request $request, Context $context): BlogSortingCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('blog-listing::load-sortings');

        $availableSortings = RequestParamHelper::get($request, 'availableSortings');
        $availableSortingsById = [];

        if (\is_array($availableSortings)) {
            $prioritiesById = [];
            foreach ($availableSortings as $id => $priority) {
                if (\is_string($id) && Uuid::isValid($id)) {
                    // non-numeric priorities sort as 0, matching SORT_NUMERIC's coercion
                    $prioritiesById[$id] = \is_numeric($priority) ? (float) $priority : 0.0;
                }
            }

            if ($prioritiesById !== []) {
                arsort($prioritiesById);
                $availableSortingsById = array_keys($prioritiesById);

                $criteria->addFilter(new EqualsAnyFilter('id', $availableSortingsById));
            }
        }

        $criteria
            ->addFilter(new EqualsFilter('active', true))
            ->addSorting(new FieldSorting('priority', 'DESC'));

        $sortings = $this->sortingRepository->search($criteria, $context)->getEntities();

        if ($availableSortingsById) {
            $sortings->sortByIdArray($availableSortingsById);
        }

        return $sortings;
    }

    private function getDefaultSortingKey(string $key, ChannelContext $context): ?string
    {
        $id = $this->systemConfigService->getString($key, $context->getChannelId());

        if (!Uuid::isValid($id)) {
            return $id;
        }

        $criteria = new Criteria([$id]);

        return $this->sortingRepository->search($criteria, $context->getContext())->getEntities()->first()?->get('key');
    }
}
