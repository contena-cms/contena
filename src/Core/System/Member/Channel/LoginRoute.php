<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\EmailIdnConverter;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class LoginRoute extends AbstractLoginRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function getDecorated(): AbstractLoginRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/account/login', name: 'channel-api.account.login', methods: ['POST'])]
    public function login(#[\SensitiveParameter] RequestDataBag $data, ChannelContext $context): ContextTokenResponse
    {
        EmailIdnConverter::encodeDataBag($data);
        $email = (string) $data->get('email', $data->get('username'));

        $combinedKey = null;
        $clientIpKey = null;
        $emailKey = null;

        if ($this->requestStack->getMainRequest() !== null) {
            $clientIpKey = (string) $this->requestStack->getMainRequest()->getClientIp();
            $emailKey = strtolower($email);
            $combinedKey = $emailKey . '-' . $clientIpKey;

            try {
                $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $combinedKey, $context->getContext());
                $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::LOGIN_USER, $emailKey, $context->getContext());
                $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::LOGIN_CLIENT, $clientIpKey, $context->getContext());
            } catch (RateLimitExceededException $exception) {
                throw MemberException::memberAuthThrottled($exception->getWaitTime(), $exception);
            }
        }

        $token = $this->accountService->loginByCredentials(
            $email,
            (string) $data->get('password'),
            $context,
        );

        if ($combinedKey !== null) {
            $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $combinedKey, $context->getContext());
        }

        if ($clientIpKey !== null) {
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_CLIENT, $clientIpKey, $context->getContext());
        }

        if ($emailKey !== null) {
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_USER, $emailKey, $context->getContext());
        }

        return new ContextTokenResponse($token);
    }
}
