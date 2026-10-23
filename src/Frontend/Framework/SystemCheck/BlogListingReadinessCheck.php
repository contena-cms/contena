<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\SystemCheck\BaseCheck;
use Contena\Core\Framework\SystemCheck\Check\Category;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomain;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainContextFactory;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class BlogListingReadinessCheck extends BaseCheck
{
    private const LISTING_PAGE = NavigationPageSeoUrlRoute::ROUTE_NAME;

    private const MESSAGE_SUCCESS = 'Blog listing pages are OK for provided sales channels.';

    private const MESSAGE_FAILURE = 'Some or all blog listing pages are unhealthy.';

    /**
     * @param ChannelRepository<CategoryCollection> $categoryRepository
     */
    public function __construct(
        private readonly ChannelDomainUtil $util,
        private readonly Connection $connection,
        private readonly AbstractChannelDomainProvider $domainProvider,
        private readonly ChannelRepository $categoryRepository,
        private readonly ChannelDomainContextFactory $contextFactory,
    ) {
    }

    public function run(): Result
    {
        return $this->util->runAsChannelRequest(
            fn () => $this->util->runWhileTrustingAllHosts(
                fn () => $this->doRun()
            )
        );
    }

    public function category(): Category
    {
        return Category::FEATURE;
    }

    public function name(): string
    {
        return 'BlogListingReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    private function doRun(): Result
    {
        $domains = $this->domainProvider->fetchChannelDomains();
        $channelIds = $domains->getKeys();
        $navigationIds = $channelIds ? $this->fetchNavigationIds($channelIds) : [];

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $channelId => $domain) {
            try {
                $navigationId = $this->resolveVisibleNavigationId($domain, $navigationIds[$channelId] ?? []);
            } catch (\Throwable $e) {
                // e.g. no context for a domain whose currency is no longer assigned, the other sales channels are still probed
                $requestStatus[Status::FAILURE->name] = Status::FAILURE;
                $extra[] = $this->util->createExceptionResult($domain->url, $e)->getVars();

                continue;
            }

            if ($navigationId === null) {
                continue;
            }

            $url = $this->util->generateDomainUrl($domain->url, self::LISTING_PAGE, [
                'navigationId' => $navigationId,
            ]);

            $request = Request::create($url);
            $result = $this->util->handleRequest($request);

            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;

            $extra[] = $result->getVars();
        }

        if ($requestStatus === []) {
            return $this->util->createEmptyResult($this->name(), 'No sales channels with blog listing pages found.');
        }

        $finalStatus = \count($requestStatus) === 1 ? current($requestStatus) : Status::ERROR;

        return new Result(
            $this->name(),
            $finalStatus,
            $finalStatus === Status::OK ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILURE,
            $finalStatus === Status::OK,
            $extra
        );
    }

    /**
     * @description The categories are loaded through the sales channel repository with the context of
     * ChannelDomainContextFactory, so extensions restricting category visibility are taken into account,
     * whether they filter the criteria (channel.category.process.criteria) or deactivate loaded categories
     * (channel.category.loaded), as Dynamic Access does. The latter is only dispatched when entities are
     * read, so the IDs alone are not enough. Otherwise the check could pick a category the frontend refuses to
     * render for an anonymous visitor, which would report an intentional restriction as an unhealthy blog
     * listing page.
     *
     * @param list<string> $candidateIds
     */
    private function resolveVisibleNavigationId(ChannelDomain $domain, array $candidateIds): ?string
    {
        if ($candidateIds === []) {
            return null;
        }

        $context = $this->contextFactory->create($domain);

        $criteria = new Criteria($candidateIds);
        $criteria->setTitle('blog-listing-readiness-check');
        $criteria->addFilter(new EqualsFilter('active', true));

        // no sorting needed: the result is only used for lookups by ID, the candidate order decides below
        $categories = $this->categoryRepository->search($criteria, $context)->getEntities();

        // keep the order of the candidates, so child listing pages are preferred over the navigation category
        foreach ($candidateIds as $candidateId) {
            // the same check NavigationPageLoader::load() applies before rendering the listing page
            if ($categories->get($candidateId)?->getActive()) {
                return $candidateId;
            }
        }

        return null;
    }

    /**
     * @description This query is necessary to determine the categories that are configured for frontend listing
     * pages per sales channel. It covers cases where the navigation category itself or one of its direct child
     * categories is assigned a CMS page of type 'blog_list', and only considers active categories and sales
     * channels. All matching categories are returned, child categories first, so the check can fall back to the
     * next candidate if a category cannot be rendered for an anonymous visitor.
     *
     * @param list<string> $channelIds
     *
     * @return array<string, list<string>>
     */
    private function fetchNavigationIds(array $channelIds): array
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(`channel`.`id`)) AS `channel_id`,
                   LOWER(HEX(`category_child`.`id`)) AS `category_id`,
                   0 AS `is_navigation_category`
            FROM `category` `category_root`
            INNER JOIN `channel`
                ON `channel`.`navigation_category_id` = `category_root`.`id`
                AND `channel`.`navigation_category_version_id` = `category_root`.`version_id`
            INNER JOIN `category` `category_child`
                ON `category_root`.`id` = `category_child`.`parent_id`
                AND `category_root`.`version_id` = `category_child`.`version_id`
                AND `category_child`.`active` = 1
            INNER JOIN `cms_page` `cms_page_child`
                ON `category_child`.`cms_page_id` = `cms_page_child`.`id`
                AND `category_child`.`version_id` = `cms_page_child`.`version_id`
                AND `cms_page_child`.`type` = 'blog_list'
            WHERE `category_root`.`active` = 1
                AND `channel`.`id` IN (:channelIds)
            UNION ALL
            SELECT LOWER(HEX(`channel`.`id`)) AS `channel_id`,
                   LOWER(HEX(`category_root`.`id`)) AS `category_id`,
                   1 AS `is_navigation_category`
            FROM `category` `category_root`
            INNER JOIN `channel`
                ON `channel`.`navigation_category_id` = `category_root`.`id`
                AND `channel`.`navigation_category_version_id` = `category_root`.`version_id`
            INNER JOIN `cms_page` `cms_page_root`
                ON `category_root`.`cms_page_id` = `cms_page_root`.`id`
                AND `category_root`.`version_id` = `cms_page_root`.`version_id`
                AND `cms_page_root`.`type` = 'blog_list'
            WHERE `category_root`.`active` = 1
                AND `channel`.`id` IN (:channelIds)
            ORDER BY `channel_id`, `is_navigation_category`, `category_id`
        SQL;

        $result = $this->connection->fetchAllAssociative(
            $sql,
            ['channelIds' => Uuid::fromHexToBytesList($channelIds)],
            ['channelIds' => ArrayParameterType::BINARY]
        );

        $navigationIds = [];
        foreach ($result as $row) {
            $navigationIds[(string) $row['channel_id']][] = (string) $row['category_id'];
        }

        return $navigationIds;
    }
}
