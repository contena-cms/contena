<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck;

use Contena\Core\Framework\SystemCheck\BaseCheck;
use Contena\Core\Framework\SystemCheck\Check\Category;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Contena\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Contena\Frontend\Framework\SystemCheck\Util\AbstractChannelDomainProvider;
use Contena\Frontend\Framework\SystemCheck\Util\ChannelDomainUtil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Frontend\Framework\HealthCheck\ChannelsReadinessCheckTest
 */
class ChannelsReadinessCheck extends BaseCheck
{
    private const string INDEX_PAGE = 'frontend.home.page';

    public function __construct(
        private readonly ChannelDomainUtil $util,
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
        return 'ChannelsReadiness';
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
            $url = $this->util->generateDomainUrl($domain->url, self::INDEX_PAGE);
            $result = $this->util->handleRequest(Request::create($url));
            $status = $result->responseCode >= Response::HTTP_BAD_REQUEST ? Status::FAILURE : Status::OK;
            $requestStatus[$status->name] = $status;
            $extra[] = $result->getVars();
        }

        $finalStatus = \count($requestStatus) === 1 ? current($requestStatus) : Status::ERROR;

        return new Result(
            $this->name(),
            $finalStatus,
            $finalStatus === Status::OK ? 'All channels are OK' : 'Some or all channels are unhealthy.',
            $finalStatus === Status::OK,
            $extra
        );
    }
}
