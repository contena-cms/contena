<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Api;

use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\OpenApi\OpenApiException;
use Contena\Core\System\Payment\OpenApi\OpenApiRouteScope;
use Contena\Core\System\Payment\Service\AbstractPaymentService;
use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\Payment\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\SubscriptionRequest;
use Contena\Core\System\Payment\Struct\TransferRequest;
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
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [OpenApiRouteScope::ID]])]
final class PaymentController
{
    public function __construct(private readonly AbstractPaymentService $paymentService)
    {
    }

    #[Route(path: '/payment-api/v1/pay', name: 'payment-api.v1.pay', methods: [Request::METHOD_POST])]
    public function pay(Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();
        $result = $this->paymentService->pay($this->app($request), new PaymentRequest(
            $this->requiredString($data, 'external_order_no', 64),
            $this->positiveInt($data, 'amount'),
            $this->optionalString($data, 'currency_code', 3) ?? 'CNY',
            $this->requiredString($data, 'method_code', 32),
            $this->requiredString($data, 'subject', 255),
            $this->optionalString($data, 'channel_code', 32),
            $this->optionalString($data, 'device_type', 32),
            $this->optionalString($data, 'client_ip', 64) ?? $request->getClientIp(),
            $this->optionalUrl($data, 'notify_url'),
            $this->optionalUrl($data, 'return_url'),
            $this->optionalArray($data, 'channel_extra'),
        ), $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }

    #[Route(path: '/payment-api/v1/query', name: 'payment-api.v1.query', methods: [Request::METHOD_POST])]
    public function query(Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();
        $result = $this->paymentService->query($this->app($request), new QueryRequest(
            $this->optionalString($data, 'order_no', 64),
            $this->optionalString($data, 'external_order_no', 64),
        ), $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }

    #[Route(path: '/payment-api/v1/refund', name: 'payment-api.v1.refund', methods: [Request::METHOD_POST])]
    public function refund(Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();
        $result = $this->paymentService->refund($this->app($request), new RefundRequest(
            $this->requiredString($data, 'external_refund_no', 64),
            $this->positiveInt($data, 'refund_amount'),
            $this->optionalString($data, 'order_no', 64),
            $this->optionalString($data, 'external_order_no', 64),
            $this->optionalString($data, 'reason', 255),
        ), $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }

    #[Route(path: '/payment-api/v1/subscribe', name: 'payment-api.v1.subscribe', methods: [Request::METHOD_POST])]
    public function subscribe(Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();
        $result = $this->paymentService->subscribe($this->app($request), new SubscriptionRequest(
            $this->requiredString($data, 'external_subscription_no', 64),
            $this->optionalString($data, 'channel_code', 32),
            $this->optionalUrl($data, 'notify_url'),
            $this->optionalUrl($data, 'return_url'),
            $this->optionalString($data, 'period_type', 16),
            $this->optionalPositiveInt($data, 'period'),
            $this->optionalDateTime($data, 'execute_time'),
            $this->optionalPositiveInt($data, 'single_amount'),
            $this->optionalPositiveInt($data, 'total_amount'),
            $this->optionalPositiveInt($data, 'total_payments'),
            $this->optionalArray($data, 'channel_extra'),
        ), $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }

    #[Route(path: '/payment-api/v1/transfer', name: 'payment-api.v1.transfer', methods: [Request::METHOD_POST])]
    public function transfer(Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();
        $result = $this->paymentService->transfer($this->app($request), new TransferRequest(
            $this->requiredString($data, 'external_transfer_no', 64),
            $this->positiveInt($data, 'amount'),
            $this->optionalString($data, 'currency_code', 3) ?? 'CNY',
            $this->requiredString($data, 'payee', 128),
            $this->requiredString($data, 'payee_name', 64),
            $this->optionalString($data, 'channel_code', 32),
            $this->optionalString($data, 'remark', 255),
            $this->optionalUrl($data, 'notify_url'),
            $this->optionalArray($data, 'channel_extra'),
        ), $context);

        return new JsonResponse(OpenApiResponse::success($result));
    }

    private function app(Request $request): PaymentAppEntity
    {
        $app = $request->attributes->get(OpenApiRouteScope::ATTRIBUTE_PAYMENT_APP);

        return $app instanceof PaymentAppEntity
            ? $app
            : throw OpenApiException::invalidSignature();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredString(array $data, string $name, int $maxLength): string
    {
        $value = $this->optionalString($data, $name, $maxLength);

        return $value ?? throw OpenApiException::missingParameter($name);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $name, int $maxLength): ?string
    {
        $value = $data[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!\is_string($value) || mb_strlen($value) > $maxLength) {
            throw OpenApiException::invalidRequest(\sprintf('Request parameter "%s" must be a string of at most %d characters.', $name, $maxLength));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function positiveInt(array $data, string $name): int
    {
        $value = $data[$name] ?? null;
        if (!\is_int($value) && !(\is_string($value) && ctype_digit($value))) {
            throw OpenApiException::invalidRequest(\sprintf('Request parameter "%s" must be a positive integer.', $name));
        }

        $value = (int) $value;
        if ($value <= 0) {
            throw OpenApiException::invalidRequest(\sprintf('Request parameter "%s" must be a positive integer.', $name));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalPositiveInt(array $data, string $name): ?int
    {
        if (!isset($data[$name]) || $data[$name] === '') {
            return null;
        }

        return $this->positiveInt($data, $name);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function optionalArray(array $data, string $name): array
    {
        $value = $data[$name] ?? [];
        if (!\is_array($value)) {
            throw OpenApiException::invalidRequest(\sprintf('Request parameter "%s" must be an object.', $name));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalUrl(array $data, string $name): ?string
    {
        $value = $this->optionalString($data, $name, 2048);
        if ($value === null) {
            return null;
        }

        $scheme = parse_url($value, \PHP_URL_SCHEME);
        if (!\in_array($scheme, ['http', 'https'], true) || filter_var($value, \FILTER_VALIDATE_URL) === false) {
            throw OpenApiException::invalidRequest(\sprintf('Request parameter "%s" must be an HTTP or HTTPS URL.', $name));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalDateTime(array $data, string $name): ?\DateTimeImmutable
    {
        $value = $this->optionalString($data, $name, 64);
        if ($value === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw OpenApiException::invalidRequest(\sprintf('Request parameter "%s" must be a valid date and time.', $name));
        }
    }
}
