<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\AbstractRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest
 */
class OpenApiRouteScope extends AbstractRouteScope
{
    final public const string ID = 'payment-api';
    final public const string ALLOWED_PATH = 'payment-api';
    final public const string ATTRIBUTE_PAYMENT_APP = 'contena-payment-app';

    protected array $allowedPaths = [self::ALLOWED_PATH];

    public function isAllowed(Request $request): bool
    {
        if (!$request->attributes->get('auth_required', true)) {
            return true;
        }

        $app = $request->attributes->get(self::ATTRIBUTE_PAYMENT_APP);
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        return $app instanceof PaymentAppEntity
            && $context instanceof Context
            && $app->tenantId === $context->getTenantId();
    }

    public function getId(): string
    {
        return self::ID;
    }
}
