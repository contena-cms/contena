<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\SystemCheck\BaseCheck;
use Contena\Core\Framework\SystemCheck\Check\Category;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class BlogListingReadinessCheck extends BaseCheck
{
    private const string MESSAGE_SUCCESS = 'Blog listing pages are OK for provided channels.';
    private const string MESSAGE_FAILURE = 'Some or all blog listing pages are unhealthy.';

    public function __construct(
        private readonly ChannelDomainUtil $util,
        private readonly Connection $connection,
        private readonly AbstractChannelDomainProvider $domainProvider,
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
        $categoryIds = $channelIds ? $this->fetchNavigationIds($channelIds) : [];
        $extra = [];
        $requestStatus = [];

        foreach ($domains as $channelId => $domain) {
            $categoryId = $categoryIds[$channelId] ?? null;
            if ($categoryId === null) {
                continue;
            }

            $url = $this->util->generateDomainUrl($domain->url, NavigationPageSeoUrlRoute::ROUTE_NAME, ['navigationId' => $categoryId]);
            $result = $this->util->handleRequest(Request::create($url));
            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;
            $extra[] = $result->getVars();
        }

        if ($requestStatus === []) {
            return $this->util->createEmptyResult($this->name(), 'No channels with blog listing pages found.');
        }

        $finalStatus = \count($requestStatus) === 1 ? current($requestStatus) : Status::ERROR;

        return new Result($this->name(), $finalStatus, $finalStatus === Status::OK ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILURE, $finalStatus === Status::OK, $extra);
    }

    /**
     * @param list<string> $channelIds
     *
     * @return array<string, string>
     */
    private function fetchNavigationIds(array $channelIds): array
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(c.id)) AS channel_id,
                   LOWER(HEX(CASE WHEN assignment_child.id IS NOT NULL THEN child.id ELSE root.id END)) AS category_id
            FROM channel c
            INNER JOIN category root
                ON c.navigation_category_id = root.id
                AND c.navigation_category_version_id = root.version_id
            LEFT JOIN category child
                ON child.parent_id = root.id
                AND child.parent_version_id = root.version_id
                AND child.active = 1
            LEFT JOIN category_content_layout assignment_child
                ON assignment_child.category_id = child.id
                AND (assignment_child.channel_id = c.id OR assignment_child.channel_id IS NULL)
            LEFT JOIN category_content_layout assignment_root
                ON assignment_root.category_id = root.id
                AND (assignment_root.channel_id = c.id OR assignment_root.channel_id IS NULL)
            WHERE c.id IN (:channelIds)
              AND c.active = 1
              AND root.active = 1
              AND (assignment_child.id IS NOT NULL OR assignment_root.id IS NOT NULL)
            GROUP BY c.id
        SQL;

        $result = $this->connection->fetchAllAssociative($sql, ['channelIds' => Uuid::fromHexToBytesList($channelIds)], ['channelIds' => ArrayParameterType::BINARY]);

        return FetchModeHelper::keyPair($result);
    }
}
