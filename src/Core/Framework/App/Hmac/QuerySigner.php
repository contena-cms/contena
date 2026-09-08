<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Hmac;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Context;
use GuzzleHttp\Psr7\Uri;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal only for use by the app-system
 */
class QuerySigner
{
    public function __construct(
        private readonly string $shopUrl,
        private readonly string $contenaVersion,
        private readonly AppLocaleProvider $localeProvider,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly ClockInterface $clock,
    ) {
    }

    public function signUri(string $uri, AppEntity $app, Context $context): UriInterface
    {
        $secret = $app->getAppSecret();
        if ($secret === null) {
            throw AppException::appSecretMissing($app->getName());
        }

        return $this->signUriFor($uri, $app->getName(), $app->getVersion(), $secret, $context);
    }

    public function signUriFor(string $uri, string $appName, string $appVersion, string $secret, Context $context): UriInterface
    {
        $unsignedUri = Uri::withQueryValues(new Uri($uri), [
            'shop-id' => $this->shopIdProvider->getShopId()->id,
            'shop-url' => $this->shopUrl,
            'timestamp' => (string) $this->clock->now()->getTimestamp(),
            'ct-version' => $this->contenaVersion,
            'app-version' => $appVersion,
            AuthMiddleware::CONTENA_CONTEXT_LANGUAGE => $context->getLanguageId(),
            AuthMiddleware::CONTENA_USER_LANGUAGE => $this->localeProvider->getLocaleFromContext($context),
            'ct-user-id' => $context->getSource() instanceof AdminApiSource ? ($context->getSource()->getUserId() ?? '') : '',
        ]);

        return Uri::withQueryValue(
            $unsignedUri,
            'contena-shop-signature',
            new RequestSigner()->signPayload($unsignedUri->getQuery(), $secret)
        );
    }
}
