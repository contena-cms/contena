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
    ) {
    }
}
