<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso;

use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\RequestAccessTokenEvent;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\RequestRefreshTokenEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\AdminUserService\ExternalAuthUser;
use Contena\Core\Framework\Sso\AdminUserService\AdminUserService;

/**
 * @internal
 */
class ContenaGrantType extends AbstractGrant
{
    public const TYPE = 'contena_grant';

    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        private readonly AdminUserService $admin_userService,
        private readonly ExternalTokenService $tokenService,
        private readonly ClockInterface $clock,
    ) {
        $this->refreshTokenRepository = $refreshTokenRepository;
    }

    public function getIdentifier(): string
    {
        return self::TYPE;
    }

    public function respondToAccessTokenRequest(ServerRequestInterface $request, ResponseTypeInterface $responseType, \DateInterval $accessTokenTTL): ResponseTypeInterface
    {
        $client = $this->getClientEntityOrFail('administration', $request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));

        $admin_user = $this->validateUser($request);
        $admin_userIdentifier = $admin_user->getIdentifier();
        $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $admin_userIdentifier);

        // take the shorter token TTL to avoid that the external token gets invalid
        $lowerTTL = TokenTimeToLive::getLowerTTL($accessTokenTTL, $this->clock->now()->diff($admin_user->expiry));

        $accessToken = $this->issueAccessToken($lowerTTL, $client, $admin_userIdentifier, $finalizedScopes);
        $this->getEmitter()->emit(new RequestAccessTokenEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request, $accessToken));
        $responseType->setAccessToken($accessToken);

        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestRefreshTokenEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request, $refreshToken));
            $responseType->setRefreshToken($refreshToken);
        }

        return $responseType;
    }

    private function validateUser(ServerRequestInterface $request): ExternalAuthUser
    {
        $code = $this->getRequestParameter('code', $request);
        if ($code === null) {
            throw SsoException::noCodeProvided();
        }

        try {
            $token = $this->tokenService->getUserToken($code);
            $admin_user = $this->admin_userService->getAndUpdateUserByExternalToken($token);
        } catch (\Throwable $exception) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw $exception;
        }

        return $admin_user;
    }
}
