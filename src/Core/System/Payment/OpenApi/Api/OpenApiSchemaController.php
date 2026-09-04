<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Api;

use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\OpenApi\OpenApiException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID], 'auth_required' => false])]
final class OpenApiSchemaController
{
    #[Route(path: '/api/payment/openapi.json', name: 'api.payment.openapi', methods: [Request::METHOD_GET])]
    public function schema(): JsonResponse
    {
        $schema = file_get_contents(__DIR__ . '/../Resources/openapi.json');
        if (!\is_string($schema)) {
            throw OpenApiException::schemaUnavailable();
        }

        return JsonResponse::fromJsonString($schema);
    }
}
