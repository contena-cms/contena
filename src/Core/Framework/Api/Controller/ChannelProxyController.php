<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller;

use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ChannelProxyController extends AbstractController
{
    private const string SEARCH_ROUTE = 'search';

    /**
     * @internal
     *
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityRepository $channelRepository,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route(
        path: '/api/_proxy/channel-api/{channelId}/{_path}',
        name: 'api.proxy.channel-api',
        requirements: ['_path' => '.*'],
    )]
    public function proxy(string $_path, string $channelId, Request $request, Context $context): Response
    {
        $channel = $this->fetchChannel($channelId, $context);

        $channelApiRequest = $this->setUpChannelApiRequest($_path, $channelId, $request, $channel, $context);

        return $this->wrapInChannelApiRoute($channelApiRequest, fn (): Response => $this->kernel->handle($channelApiRequest, HttpKernelInterface::SUB_REQUEST));
    }

    /**
     * @param callable(): Response $call
     */
    private function wrapInChannelApiRoute(Request $request, callable $call): Response
    {
        $requestStackBackup = $this->clearRequestStackWithBackup($this->requestStack);
        $this->requestStack->push($request);

        try {
            return $call();
        } finally {
            $this->restoreRequestStack($this->requestStack, $requestStackBackup);
        }
    }

    private function setUpChannelApiRequest(
        string $path,
        string $channelId,
        Request $request,
        ChannelEntity $channel,
        Context $context,
    ): Request {
        $contextToken = $this->getContextToken($request);

        $server = array_merge($request->server->all(), ['REQUEST_URI' => '/channel-api/' . $path]);
        $subrequest = $request->duplicate(null, null, [], null, null, $server);

        $subrequest->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $channel->getAccessKey());
        $subrequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $channel->getAccessKey());

        $channelContext = $this->fetchChannelContext($channelId, $subrequest, $context);

        if ($path === self::SEARCH_ROUTE) {
            $channelContext->getContext()->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
        }

        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);
        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $channelContext->getContext());

        return $subrequest;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function fetchChannel(string $channelId, Context $context): ChannelEntity
    {
        $channel = $this->channelRepository->search(new Criteria([$channelId]), $context)->getEntities()->get($channelId);

        if ($channel === null) {
            throw ApiException::invalidChannelId($channelId);
        }

        return $channel;
    }

    private function getContextToken(Request $request): string
    {
        return $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
            ?? Random::getAlphanumericString(32);
    }

    /**
     * @return list<Request>
     */
    private function clearRequestStackWithBackup(RequestStack $requestStack): array
    {
        $requestStackBackup = [];

        while ($requestStack->getMainRequest()) {
            $request = $requestStack->pop();

            if ($request === null) {
                continue;
            }

            $requestStackBackup[] = $request;
        }

        return $requestStackBackup;
    }

    /**
     * @param list<Request> $requestStackBackup
     */
    private function restoreRequestStack(RequestStack $requestStack, array $requestStackBackup): void
    {
        $this->clearRequestStackWithBackup($requestStack);

        foreach ($requestStackBackup as $backedUpRequest) {
            $requestStack->push($backedUpRequest);
        }
    }

    private function fetchChannelContext(
        string $channelId,
        Request $request,
        Context $originalContext,
    ): ChannelContext {
        return $this->contextService->get(new ChannelContextServiceParameters(
            $channelId,
            $this->getContextToken($request),
            $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
            originalContext: $originalContext,
        ));
    }
}
