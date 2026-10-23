<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Hmac;

use GuzzleHttp\Psr7\Uri;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\UriInterface;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Store\InAppPurchase;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\Store\Authentication\LocaleProvider;

/**
 * @internal only for use by the app-system
 */
class QuerySigner
{

    /**
     * @internal
     */
    public function __construct(
        private readonly string $shopUrl,
        private readonly string $shopwareVersion,
        private readonly LocaleProvider $localeProvider,
        private readonly InstallationIdProvider $installationIdProvider,
        private readonly InAppPurchase $inAppPurchase,
        private readonly ClockInterface $clock
    ) {
    }


    public function signUri(string $uri, AppEntity $app, Context $context): UriInterface
    {
        $secret = $app->getAppSecret();
        if ($secret === null) {
            throw AppException::appSecretMissing($app->getName());
        }

        $unsignedUri = Uri::withQueryValues(new Uri($uri), [
            'shop-id' => $this->installationIdProvider->getInstallationId()->id,
            'shop-url' => $this->shopUrl,
            'timestamp' => (string) $this->clock->now()->getTimestamp(),
            'sw-version' => $this->contenaVersion,
            'app-version' => $app->getVersion(),
            'in-app-purchases' => \urlencode($this->inAppPurchase->getJWTByExtension($app->getName()) ?? ''),
            AuthMiddleware::CONTENA_CONTEXT_LANGUAGE => $context->getLanguageId(),
            AuthMiddleware::CONTENA_USER_LANGUAGE => $this->localeProvider->getLocaleFromContext($context),
            'sw-admin-user-id' => $context->getSource() instanceof AdminApiSource ? ($context->getSource()->getUserId() ?? '') : '',
        ]);

        return Uri::withQueryValue(
            $unsignedUri,
            'contena-shop-signature',
            (new RequestSigner())->signPayload($unsignedUri->getQuery(), $secret)
        );
    }
}
