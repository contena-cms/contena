<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\Service\AbstractPaymentService;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\Payment\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\SubscriptionRequest;
use Contena\Core\System\Payment\Struct\TransferRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID], 'auth_required' => false, 'payment_auth_required' => true])]
final class PaymentController
{
    public function __construct(
        private readonly AbstractPaymentService $paymentService,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route(path: '/api/payment/pay', name: 'api.payment.pay', methods: [Request::METHOD_POST])]
    public function pay(
        PaymentAppEntity $app,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        PaymentRequest $paymentRequest,
        Context $context,
    ): JsonResponse {
        $paymentRequest->clientIp = $this->requestStack->getCurrentRequest()?->getClientIp();

        return new JsonResponse(OpenApiResponse::success($this->paymentService->pay($app, $paymentRequest, $context)));
    }

    #[Route(path: '/api/payment/query', name: 'api.payment.query', methods: [Request::METHOD_POST])]
    public function query(
        PaymentAppEntity $app,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        QueryRequest $queryRequest,
        Context $context,
    ): JsonResponse {
        $result = $this->paymentService->query($app, $queryRequest, $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }

    #[Route(path: '/api/payment/refund', name: 'api.payment.refund', methods: [Request::METHOD_POST])]
    public function refund(
        PaymentAppEntity $app,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        RefundRequest $refundRequest,
        Context $context,
    ): JsonResponse {
        $result = $this->paymentService->refund($app, $refundRequest, $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }

    #[Route(path: '/api/payment/subscribe', name: 'api.payment.subscribe', methods: [Request::METHOD_POST])]
    public function subscribe(
        PaymentAppEntity $app,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        SubscriptionRequest $subscriptionRequest,
        Context $context,
    ): JsonResponse {
        $result = $this->paymentService->subscribe($app, $subscriptionRequest, $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }

    #[Route(path: '/api/payment/transfer', name: 'api.payment.transfer', methods: [Request::METHOD_POST])]
    public function transfer(
        PaymentAppEntity $app,
        #[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        TransferRequest $transferRequest,
        Context $context,
    ): JsonResponse {
        $result = $this->paymentService->transfer($app, $transferRequest, $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }
}
