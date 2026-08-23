<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\SystemCheck\Util;

use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\ChannelRequest;
use Contena\Core\Defaults;
use Contena\Core\Framework\SystemCheck\Check\Result;
use Contena\Core\Framework\SystemCheck\Check\Status;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
readonly class ChannelDomainUtil
{
    private const int MAX_REDIRECTS = 5;

    public function __construct(
        private RouterInterface $router,
        private RequestStack $requestStack,
        private KernelInterface $kernel,
        private LoggerInterface $logger,
        private ClockInterface $clock,
    ) {
    }

    public function runAsChannelRequest(callable $callback): Result
    {
        $mainRequest = $this->requestStack->getMainRequest();
        if ($mainRequest === null) {
            return $callback();
        }

        $hasChannelRequest = $mainRequest->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST);
        $mainRequest->attributes->set(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, true);

        try {
            return $callback();
        } finally {
            $mainRequest->attributes->set(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, $hasChannelRequest);
        }
    }

    public function runWhileTrustingAllHosts(callable $callback): Result
    {
        $trustedHosts = array_map(
            static fn (string $pattern) => preg_replace('/^\{(.*)\}i$/', '$1', $pattern),
            Request::getTrustedHosts()
        );

        Request::setTrustedHosts([]);
        try {
            return $callback();
        } finally {
            Request::setTrustedHosts($trustedHosts);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generateDomainUrl(string $url, string $routeName, array $parameters = []): string
    {
        return rtrim($url, '/') . $this->router->generate($routeName, $parameters);
    }

    public function createEmptyResult(string $name, string $message): Result
    {
        return new Result($name, Status::SKIPPED, $message, true, []);
    }

    public function handleRequest(Request $request): FrontendHealthCheckResult
    {
        $currentRequest = $request;
        $responseTime = 0.0;
        $redirectCount = 0;
        $response = null;

        while ($redirectCount <= self::MAX_REDIRECTS) {
            $requestStart = (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT);
            try {
                $response = $this->kernel->handle($currentRequest, catch: false);
            } catch (\Exception $exception) {
                $responseTime += (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT) - $requestStart;

                $this->logger->error(\sprintf('Error during systemcheck: "%s"', $exception->getMessage()), ['exception' => $exception, 'request' => $currentRequest]);

                return FrontendHealthCheckResult::create($currentRequest->getUri(), Response::HTTP_BAD_REQUEST, $responseTime, $exception->getMessage());
            }
            $responseTime += (float) $this->clock->now()->format(Defaults::MICROTIME_FORMAT) - $requestStart;

            if (!$response instanceof RedirectResponse) {
                break;
            }

            ++$redirectCount;
            $currentRequest = Request::create($response->getTargetUrl());
        }

        if ($redirectCount > self::MAX_REDIRECTS) {
            return FrontendHealthCheckResult::create($currentRequest->getUri(), Response::HTTP_LOOP_DETECTED, $responseTime);
        }

        return FrontendHealthCheckResult::create($currentRequest->getUri(), $response->getStatusCode(), $responseTime);
    }
}
