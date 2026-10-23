<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck;

use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\SystemCheck\BaseCheck;
use Contena\Core\Framework\SystemCheck\Check\Category;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomain;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainContextFactory;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class BlogDetailReadinessCheck extends BaseCheck
{
    private const MESSAGE_SUCCESS = 'Blog detail pages are OK for provided sales channels.';

    private const MESSAGE_FAILURE = 'Some or all blog detail pages are unhealthy.';

    /**
     * @param ChannelRepository<ChannelBlogCollection> $blogRepository
     */
    public function __construct(
        private readonly ChannelDomainUtil $util,
        private readonly AbstractChannelDomainProvider $domainProvider,
        private readonly ChannelRepository $blogRepository,
        private readonly ChannelDomainContextFactory $contextFactory,
        private readonly AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory,
        private readonly SystemConfigService $systemConfigService,
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
        return 'BlogDetailReadiness';
    }

    protected function allowedSystemCheckExecutionContexts(): array
    {
        return SystemCheckExecutionContext::readiness();
    }

    private function doRun(): Result
    {
        $domains = $this->domainProvider->fetchChannelDomains();

        $extra = [];
        $requestStatus = [];
        foreach ($domains as $domain) {
            try {
                $blogId = $this->fetchVisibleBlogId($domain);
            } catch (\Throwable $e) {
                // e.g. no context for a domain whose currency is no longer assigned, the other sales channels are still probed
                $requestStatus[Status::FAILURE->name] = Status::FAILURE;
                $extra[] = $this->util->createExceptionResult($domain->url, $e)->getVars();

                continue;
            }

            if ($blogId === null) {
                continue;
            }

            $url = $this->util->generateDomainUrl($domain->url, BlogPageSeoUrlRoute::ROUTE_NAME, [
                'blogId' => $blogId,
            ]);

            $request = Request::create($url);
            $result = $this->util->handleRequest($request);

            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;

            $extra[] = $result->getVars();
        }

        if ($requestStatus === []) {
            return $this->util->createEmptyResult($this->name(), 'No sales channels with blog detail pages found.');
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
     * @description The blog is resolved through the sales channel repository with the context of
     * ChannelDomainContextFactory on purpose, so the same criteria processing the frontend applies is
     * used: ChannelBlogDefinition::processCriteria() adds the BlogAvailableFilter that
     * BlogDetailRoute uses, and extensions restricting blog visibility by rules (for example via the
     * channel.blog.process.criteria event) are taken into account as well. Otherwise the check could
     * pick a blog the frontend refuses to render for an anonymous visitor, which would report an
     * intentional restriction as an unhealthy blog detail page.
     */
    private function fetchVisibleBlogId(ChannelDomain $domain): ?string
    {
        $context = $this->contextFactory->create($domain);

        $criteria = new Criteria();
        $criteria->setTitle('blog-detail-readiness-check');
        // Mirrors BlogDetailRoute::addCloseoutFilter()
        if ($this->systemConfigService->getBool('core.listing.hideCloseoutBlogsWhenOutOfStock', $domain->channelId)) {
            $criteria->addFilter($this->blogCloseoutFilterFactory->create($context));
        }

        // pick the same blog on every run, so the check result is reproducible
        $criteria->addSorting(new FieldSorting('id'));
        $criteria->setLimit(1);

        return $this->blogRepository->searchIds($criteria, $context)->firstId();
    }
}
