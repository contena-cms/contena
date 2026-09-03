<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Struct\PaymentResult;
use Psr\Http\Message\ResponseInterface;
use Yansongda\Artful\Rocket;
use Yansongda\Supports\Collection;

/**
 * @internal
 */
final class ProviderResultMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function data(mixed $result): array
    {
        if ($result instanceof Collection) {
            return $result->all();
        }
        if ($result instanceof Rocket) {
            return self::data($result->getDestination() ?? $result->getPayload());
        }
        if ($result instanceof ResponseInterface) {
            return [
                '_http_status' => $result->getStatusCode(),
                '_headers' => $result->getHeaders(),
                '_body' => (string) $result->getBody(),
            ];
        }
        if (\is_array($result)) {
            return $result;
        }

        return ['value' => $result];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function paymentResult(array $data, string $status, ?string $method = null): PaymentResult
    {
        $action = PaymentResult::ACTION_NONE;
        $actionValue = null;

        if (\is_string($data['_body'] ?? null) && $data['_body'] !== '') {
            $action = $method === 'app' ? PaymentResult::ACTION_CLIENT : PaymentResult::ACTION_HTML;
            $actionValue = $data['_body'];
        } elseif (\is_string($data['h5_url'] ?? null)) {
            $action = PaymentResult::ACTION_REDIRECT;
            $actionValue = $data['h5_url'];
        } elseif (\is_string($data['code_url'] ?? null) || \is_string($data['qr_code'] ?? null)) {
            $action = PaymentResult::ACTION_QR_CODE;
            $actionValue = (string) ($data['code_url'] ?? $data['qr_code']);
        } elseif (isset($data['prepay_id']) || isset($data['paySign'])) {
            $action = PaymentResult::ACTION_CLIENT;
            $actionValue = json_encode($data, \JSON_THROW_ON_ERROR);
        }

        return new PaymentResult(
            $status,
            $action,
            $actionValue,
            self::string($data, 'out_trade_no', 'out_request_no', 'out_batch_no'),
            self::string($data, 'trade_no', 'transaction_id', 'refund_id', 'batch_id', 'contract_id'),
            self::string($data, 'code', 'result_code'),
            self::string($data, 'msg', 'message', 'sub_msg'),
            $data,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function string(array $data, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (\is_scalar($data[$key] ?? null)) {
                return (string) $data[$key];
            }
        }

        return null;
    }
}
