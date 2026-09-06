<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Struct;

use Contena\Core\Framework\Struct\Struct;

/**
 * Describes the client interaction required to continue a payment operation.
 *
 * @codeCoverageIgnore
 */
final class PaymentAction extends Struct
{
    final public const string NONE = 'none';
    final public const string REDIRECT = 'redirect';
    final public const string HTML = 'html';
    final public const string QR_CODE = 'qr_code';
    final public const string CLIENT = 'client';

    public function __construct(
        public readonly string $type,
        public readonly ?string $value = null,
    ) {
    }
}
