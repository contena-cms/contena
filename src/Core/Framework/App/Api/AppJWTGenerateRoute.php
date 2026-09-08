<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Api;

use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class AppJWTGenerateRoute
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/channel-api/app-system/{name}/generate-token', name: 'channel-api.app-system.generate-token', methods: ['POST'])]
    public function generate(string $name, ChannelContext $context): JsonResponse
    {
        if ($context->getMember() === null) {
            throw AppException::jwtGenerationRequiresMemberLoggedIn();
        }

        ['app_secret' => $appSecret, 'privileges' => $privileges] = $this->fetchAppDetails($name);

        $key = InMemory::plainText($appSecret);

        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            $key
        );

        $expiration = $this->clock->now()->modify('+10 minutes');

        /** @var non-empty-string $shopId */
        $shopId = $this->shopIdProvider->getShopId()->id;
        $builder = $configuration
            ->builder()
            ->issuedBy($shopId)
            ->issuedAt($this->clock->now())
            ->canOnlyBeUsedAfter($this->clock->now())
            ->expiresAt($expiration);

        if (\in_array('channel:read', $privileges, true)) {
            $builder = $builder->withClaim('channelId', $context->getChannelId());
        }

        if (\in_array('member:read', $privileges, true)) {
            $builder = $builder->withClaim('memberId', $context->getMemberId());
        }

        if (\in_array('language:read', $privileges, true)) {
            $builder = $builder->withClaim('languageId', $context->getLanguageId());
        }

        return new JsonResponse([
            'token' => $builder->getToken($configuration->signer(), $configuration->signingKey())->toString(),
            'expires' => $expiration->format(\DateTime::ATOM),
            'shopId' => $shopId,
        ]);
    }

    /**
     * @return array{app_secret: non-empty-string, privileges: array<string>}
     */
    private function fetchAppDetails(string $name): array
    {
        $row = $this->connection->fetchAssociative('SELECT
    `app`.app_secret,
    `acl_role`.privileges
FROM `app`
LEFT JOIN acl_role ON app.acl_role_id = acl_role.id
WHERE `app`.name = ? AND
      active = 1', [$name]);

        if ($row === false) {
            throw AppException::notFound($name);
        }

        $row['privileges'] = json_decode($row['privileges'], true, 512, \JSON_THROW_ON_ERROR);

        /** @phpstan-ignore-next-line PHPStan could not recognize the loaded array shape from the database */
        return $row;
    }
}
