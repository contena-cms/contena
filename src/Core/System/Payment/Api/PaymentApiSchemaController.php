<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Api;

use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Payment\PaymentApiTest
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [PaymentApiRouteScope::ID], 'auth_required' => false])]
final class PaymentApiSchemaController
{
    #[Route(path: '/payment-api/v1/openapi.json', name: 'payment-api.v1.openapi', methods: [Request::METHOD_GET])]
    public function schema(): JsonResponse
    {
        $schema = file_get_contents(__DIR__ . '/Resources/openapi.json');
        if (!\is_string($schema)) {
            throw PaymentApiException::schemaUnavailable();
        }

        return JsonResponse::fromJsonString($schema);
    }
}
