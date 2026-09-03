<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Payment\Gateway\PaymentStatus;

final class PaymentResult extends Struct
{
    final public const string ACTION_NONE = 'none';
    final public const string ACTION_REDIRECT = 'redirect';
    final public const string ACTION_HTML = 'html';
    final public const string ACTION_QR_CODE = 'qr_code';
    final public const string ACTION_CLIENT = 'client';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $status = PaymentStatus::UNKNOWN,
        public readonly string $action = self::ACTION_NONE,
        public readonly ?string $actionValue = null,
        public readonly ?string $providerRequestId = null,
        public readonly ?string $providerResourceId = null,
        public readonly ?string $resultCode = null,
        public readonly ?string $resultMessage = null,
        public readonly array $data = [],
        public readonly ?string $resourceNo = null,
        public readonly ?string $externalResourceNo = null,
        public readonly ?string $transactionNo = null,
    ) {
    }

    public function withResource(string $resourceNo, string $externalResourceNo, ?string $transactionNo = null): self
    {
        return new self(
            $this->status,
            $this->action,
            $this->actionValue,
            $this->providerRequestId,
            $this->providerResourceId,
            $this->resultCode,
            $this->resultMessage,
            $this->data,
            $resourceNo,
            $externalResourceNo,
            $transactionNo,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'action' => $this->action,
            'actionValue' => $this->actionValue,
            'providerRequestId' => $this->providerRequestId,
            'providerResourceId' => $this->providerResourceId,
            'resultCode' => $this->resultCode,
            'resultMessage' => $this->resultMessage,
            'data' => $this->data,
        ];
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data, string $fallbackStatus = PaymentStatus::UNKNOWN): self
    {
        if ($data === null) {
            return new self($fallbackStatus);
        }

        return new self(
            self::string($data['status'] ?? null) ?? $fallbackStatus,
            self::string($data['action'] ?? null) ?? self::ACTION_NONE,
            self::string($data['actionValue'] ?? null),
            self::string($data['providerRequestId'] ?? null),
            self::string($data['providerResourceId'] ?? null),
            self::string($data['resultCode'] ?? null),
            self::string($data['resultMessage'] ?? null),
            \is_array($data['data'] ?? null) ? $data['data'] : [],
        );
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
