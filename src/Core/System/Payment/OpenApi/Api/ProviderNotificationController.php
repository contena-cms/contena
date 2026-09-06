<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Api;

use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\Notification\GatewayNotificationService;
use Contena\Core\System\Payment\Notification\Struct\GatewayNotification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID], 'auth_required' => false])]
final class ProviderNotificationController
{
    public function __construct(private readonly GatewayNotificationService $notificationService)
    {
    }

    #[Route(
        path: '/api/payment/notify/{channel}/{channelConfigId}',
        name: 'api.payment.notify',
        requirements: ['channelConfigId' => '[0-9a-f]{32}'],
        methods: [Request::METHOD_POST],
    )]
    public function notify(string $channel, string $channelConfigId, Request $request): Response
    {
        $result = $this->notificationService->process($channel, $channelConfigId, new GatewayNotification(
            $request->getContent(),
            $this->headers($request),
            $request->request->all(),
        ));

        return new Response($result->body, $result->status, ['Content-Type' => $result->contentType]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function headers(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = array_values(array_filter($values, \is_string(...)));
        }

        return $headers;
    }
}
