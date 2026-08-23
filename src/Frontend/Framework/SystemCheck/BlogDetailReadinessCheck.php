<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck;

use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\SystemCheck\BaseCheck;
use Contena\Core\Framework\SystemCheck\Check\Category;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class BlogDetailReadinessCheck extends BaseCheck
{
    private const MESSAGE_SUCCESS = 'Blog detail pages are OK for provided channels.';
    private const MESSAGE_FAILURE = 'Some or all blog detail pages are unhealthy.';

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

        foreach ($domains as $channelId => $domain) {
            $blogId = $this->fetchActiveBlogIdByChannelId($channelId);
            if ($blogId === null) {
                continue;
            }

            $url = $this->util->generateDomainUrl($domain->url, BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => $blogId]);
            $result = $this->util->handleRequest(Request::create($url));
            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;
            $extra[] = $result->getVars();
        }

        if ($requestStatus === []) {
            return $this->util->createEmptyResult($this->name(), 'No channels with blog detail pages found.');
        }

        $finalStatus = \count($requestStatus) === 1 ? current($requestStatus) : Status::ERROR;

        return new Result($this->name(), $finalStatus, $finalStatus === Status::OK ? self::MESSAGE_SUCCESS : self::MESSAGE_FAILURE, $finalStatus === Status::OK, $extra);
    }

    private function fetchActiveBlogIdByChannelId(string $channelId): ?string
    {
        $sql = <<<'SQL'
            SELECT LOWER(HEX(b.id)) AS blog_id
            FROM blog b
            WHERE b.version_id = :versionId
              AND b.active = 1
              AND EXISTS (
                  SELECT 1 FROM blog_visibility bv
                  WHERE bv.blog_id = b.id
                    AND bv.blog_version_id = b.version_id
                    AND bv.channel_id = :channelId
              )
            ORDER BY b.id
            LIMIT 1
        SQL;

        return $this->connection->fetchOne($sql, [
            'channelId' => Uuid::fromHexToBytes($channelId),
            'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]) ?: null;
    }
}
