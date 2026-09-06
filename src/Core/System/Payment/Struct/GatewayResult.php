<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\Payment\Gateway\PaymentStatus;

final class GatewayResult extends Struct
{
    public function __construct(
        public readonly string $status = PaymentStatus::UNKNOWN,
        public readonly ?PaymentAction $action = null,
        public readonly GatewayResponse $response = new GatewayResponse(),
    ) {
    }

    /**
     * Keeps the persisted representation stable while the in-memory model remains explicit.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'action' => $this->action === null ? PaymentAction::NONE : $this->action->type,
            'actionValue' => $this->action?->value,
            'providerRequestId' => $this->response->requestId,
            'providerResourceId' => $this->response->resourceId,
            'resultCode' => $this->response->code,
            'resultMessage' => $this->response->message,
            'data' => $this->response->data,
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

        $actionType = self::string($data['action'] ?? null);

        return new self(
            self::string($data['status'] ?? null) ?? $fallbackStatus,
            $actionType === null || $actionType === PaymentAction::NONE
                ? null
                : new PaymentAction($actionType, self::string($data['actionValue'] ?? null)),
            new GatewayResponse(
                self::string($data['providerRequestId'] ?? null),
                self::string($data['providerResourceId'] ?? null),
                self::string($data['resultCode'] ?? null),
                self::string($data['resultMessage'] ?? null),
                \is_array($data['data'] ?? null) ? $data['data'] : [],
            ),
        );
    }

    private static function string(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
