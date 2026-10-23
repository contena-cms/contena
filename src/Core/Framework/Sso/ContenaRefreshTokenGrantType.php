<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;
use Contena\Core\Framework\Api\OAuth\RefreshToken;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\AdminUserService\ExternalAuthUser;
use Contena\Core\Framework\Sso\AdminUserService\Token;
use Contena\Core\Framework\Sso\AdminUserService\AdminUserService;

/**
 * @internal
 */
class ContenaRefreshTokenGrantType extends RefreshTokenGrant
{
    private ?string $refreshTokenFamilyId = null;

    public function __construct(
        private readonly RefreshTokenRepository $contenaRefreshTokenRepository,
        private readonly AdminUserService $admin_userService,
        private readonly ExternalTokenService $tokenService,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($contenaRefreshTokenRepository);
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $this->refreshTokenFamilyId = null;
        $client = $this->validateClient($request);
        $oldRefreshToken = $this->validateOldRefreshToken($request, $client->getIdentifier());
        $this->refreshTokenFamilyId = $this->contenaRefreshTokenRepository->getRefreshTokenFamilyId($oldRefreshToken['refresh_token_id']);
        if ($this->refreshTokenFamilyId === null) {
            throw OAuthServerException::invalidRefreshToken('Token has been revoked');
        }

        $admin_userId = $oldRefreshToken['admin_user_id'];

        $oAuthUser = $this->admin_userService->searchOAuthUserByUserId($admin_userId);
        if ($oAuthUser instanceof ExternalAuthUser && $oAuthUser->token instanceof Token) {
            $newSsoTokenResult = $this->tokenService->getUserTokenByRefreshToken($oAuthUser->token->refreshToken);
            $oAuthUser = $this->admin_userService->updateOAuthUserWithNewToken($oAuthUser, $newSsoTokenResult);
            $this->admin_userService->saveOAuthUser($oAuthUser);

            // take the shorter token TTL to avoid that the external token gets invalid
            $accessTokenTTL = TokenTimeToLive::getLowerTTL($accessTokenTTL, new \DateInterval('PT' . $newSsoTokenResult->expiresIn . 'S'));
        }

        return parent::respondToAccessTokenRequest($request, $responseType, $accessTokenTTL);
    }

    protected function issueRefreshToken(AccessTokenEntityInterface $accessToken): ?RefreshTokenEntityInterface
    {
        if ($this->supportsGrantType($accessToken->getClient(), 'refresh_token') === false) {
            return null;
        }

        $refreshToken = $this->refreshTokenRepository->getNewRefreshToken();
        if (!$refreshToken instanceof RefreshToken) {
            return null;
        }

        $refreshToken->setExpiryDateTime($this->clock->now()->add($this->refreshTokenTTL));
        $refreshToken->setAccessToken($accessToken);
        if ($this->refreshTokenFamilyId !== null) {
            $refreshToken->setFamilyId($this->refreshTokenFamilyId);
        }

        $maxGenerationAttempts = self::MAX_RANDOM_TOKEN_GENERATION_ATTEMPTS;

        while ($maxGenerationAttempts-- > 0) {
            $refreshToken->setIdentifier($this->generateUniqueIdentifier());

            try {
                $this->refreshTokenRepository->persistNewRefreshToken($refreshToken);

                return $refreshToken;
            } catch (UniqueTokenIdentifierConstraintViolationException $exception) {
                if ($maxGenerationAttempts === 0) {
                    throw $exception;
                }
            }
        }

        return $refreshToken;
    }
}
