<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\RequiredConstraintsViolated;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
readonly class SymfonyBearerTokenValidator
{
    /**
     * @internal
     */
    public function __construct(
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private Connection $connection,
        private Configuration $jwtConfiguration
    ) {
    }

    public function validateAuthorization(Request $request): void
    {
        if ($request->headers->has('authorization') === false) {
            throw OAuthServerException::accessDenied('Missing "Authorization" header');
        }

        $header = $request->headers->get('authorization', '');
        $jwt = \trim((string) \preg_replace('/^\s*Bearer\s/', '', $header));

        if ($jwt === '') {
            throw OAuthServerException::accessDenied('Missing token in "Authorization" header');
        }

        try {
            // Attempt to parse the JWT
            /** @var UnencryptedToken $token */
            $token = $this->jwtConfiguration->parser()->parse($jwt);
        } catch (Exception $exception) {
            throw OAuthServerException::accessDenied($exception->getMessage(), null, $exception);
        }

        try {
            // Attempt to validate the JWT
            $constraints = $this->jwtConfiguration->validationConstraints();
            $this->jwtConfiguration->validator()->assert($token, ...$constraints);
        } catch (RequiredConstraintsViolated $exception) {
            throw OAuthServerException::accessDenied('Access token could not be verified', null, $exception);
        }

        $claims = $token->claims();

        // Check if the token has been revoked
        if ($this->accessTokenRepository->isAccessTokenRevoked($claims->get('jti'))) {
            throw OAuthServerException::accessDenied('Access token has been revoked');
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, $claims->get('jti'));
        $aud = $claims->get('aud');

        if (\is_array($aud)) {
            $aud = array_shift($aud);
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $aud);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_SCOPES, $claims->get('scopes'));

        $admin_userId = $claims->get('sub');

        // for integrations we get the access key as "sub", we only want to set it when it is the real admin_user id
        if ($admin_userId && Uuid::isValid($admin_userId)) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID, $admin_userId);
            $this->validateAccessTokenIssuedAt($token->claims()->get('iat', 0), $admin_userId);
        }
    }

    private function validateAccessTokenIssuedAt(\DateTimeImmutable $tokenIssuedAt, string $admin_userId): void
    {
        $admin_user = $this->connection->createQueryBuilder()
            ->select('last_updated_password_at', 'active')
            ->from('admin_user')
            ->where('id = :admin_userId')
            ->setParameter('admin_userId', Uuid::fromHexToBytes($admin_userId))
            ->executeQuery()
            ->fetchAssociative();

        if ($admin_user === false || !(bool) $admin_user['active']) {
            throw OAuthServerException::accessDenied('Access token is invalid');
        }

        $lastUpdatedPasswordAt = $admin_user['last_updated_password_at'];

        if ($lastUpdatedPasswordAt === null) {
            return;
        }

        $lastUpdated = new \DateTime($lastUpdatedPasswordAt);

        if ($lastUpdated > $tokenIssuedAt) {
            throw OAuthServerException::accessDenied('Access token is expired');
        }
    }
}
