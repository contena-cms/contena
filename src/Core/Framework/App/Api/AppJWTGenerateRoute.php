<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Api;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Store\InAppPurchase;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class AppJWTGenerateRoute
{

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly InstallationIdProvider $installationIdProvider,
        private readonly InAppPurchase $inAppPurchase,
        private readonly ClockInterface $clock
    ) {
    }


    #[Route('/channel-api/app-system/{name}/generate-token', name: 'channel-api.app-system.generate-token', methods: ['POST'])]
    public function generate(string $name, ChannelContext $context): JsonResponse
    {
        if ($context->getUser() === null) {
            throw AppException::jwtGenerationRequiresUserLoggedIn();
        }

        ['app_secret' => $appSecret, 'privileges' => $privileges] = $this->fetchAppDetails($name);

        $key = InMemory::plainText($appSecret);

        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            $key
        );

        $expiration = $this->clock->now()->modify('+10 minutes');

        /** @var non-empty-string $installationId */
        $installationId = $this->installationIdProvider->getInstallationId()->id;
        $builder = $configuration
            ->builder()
            ->issuedBy($installationId)
            ->issuedAt($this->clock->now())
            ->canOnlyBeUsedAfter($this->clock->now())
            ->expiresAt($expiration);

        $builder = $builder->withClaim('inAppPurchases', $this->inAppPurchase->getJWTByExtension($name));

        if (\in_array('channel:read', $privileges, true)) {
            $builder = $builder->withClaim('channelId', $context->getChannelId());
        }

        if (\in_array('user:read', $privileges, true)) {
            $builder = $builder->withClaim('userId', $context->getUserId());
        }

        if (\in_array('currency:read', $privileges, true)) {
            $builder = $builder->withClaim('currencyId', $context->getCurrencyId());
        }

        if (\in_array('language:read', $privileges, true)) {
            $builder = $builder->withClaim('languageId', $context->getLanguageId());
        }

        if (\in_array('payment_method:read', $privileges, true)) {
            $builder = $builder->withClaim('paymentMethodId', $context->getPaymentMethod()->getId());
        }

        if (\in_array('shipping_method:read', $privileges, true)) {
            $builder = $builder->withClaim('shippingMethodId', $context->getShippingMethod()->getId());
        }

        return new JsonResponse([
            'token' => $builder->getToken($configuration->signer(), $configuration->signingKey())->toString(),
            'expires' => $expiration->format(\DateTime::ATOM),
            'installationId' => $installationId,
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
