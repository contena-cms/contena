<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Checkout\Customer\Extension\LoginRouteExtension;
use Contena\Core\Checkout\Customer\Service\EmailIdnConverter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LoginRoute extends AbstractLoginRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractLoginRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/account/login', name: 'store-api.account.login', methods: ['POST'])]
    public function login(#[\SensitiveParameter] RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        return $this->extensions->publish(
            name: LoginRouteExtension::NAME,
            extension: new LoginRouteExtension($data, $context),
            function: $this->_login(...),
        );
    }

    private function _login(#[\SensitiveParameter] RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
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
                $this->rateLimiter->ensureAccepted(RateLimiter::LOGIN_ROUTE, $combinedKey);
                $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::LOGIN_USER, $emailKey);
                $this->rateLimiter->ensureAcceptedIfConfigured(RateLimiter::LOGIN_CLIENT, $clientIpKey);
            } catch (RateLimitExceededException $exception) {
                throw CustomerException::customerAuthThrottledException($exception->getWaitTime(), $exception);
            }
        }

        $token = $this->accountService->loginByCredentials(
            $email,
            (string) $data->get('password'),
            $context
        );

        if ($combinedKey !== null) {
            $this->rateLimiter->reset(RateLimiter::LOGIN_ROUTE, $combinedKey);
        }

        if ($clientIpKey !== null) {
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_CLIENT, $clientIpKey);
        }

        if ($emailKey !== null) {
            $this->rateLimiter->resetIfConfigured(RateLimiter::LOGIN_USER, $emailKey);
        }

        return new ContextTokenResponse($token);
    }
}
