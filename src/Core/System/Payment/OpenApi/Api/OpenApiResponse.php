<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\OpenApi\Api;

use Contena\Core\System\Payment\Struct\PaymentResult;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\Payment\OpenApi\OpenApiTest
 */
final class OpenApiResponse
{
    final public const string SUCCESS = 'SUCCESS';

    /**
     * @return array{code: string, message: string, data: array<string, mixed>}
     */
    public static function success(PaymentResult $result): array
    {
        return [
            'code' => self::SUCCESS,
            'message' => 'ok',
            'data' => array_filter([
                'resource_no' => $result->resourceNo,
                'external_resource_no' => $result->externalResourceNo,
                'transaction_no' => $result->transactionNo,
                'status' => $result->status,
                'action' => $result->action,
                'action_value' => $result->actionValue,
            ], static fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * @return array{code: string, message: string, data: null}
     */
    public static function error(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message, 'data' => null];
    }
}
