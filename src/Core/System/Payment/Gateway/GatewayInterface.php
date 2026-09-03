<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Gateway;

interface GatewayInterface
{
    final public const string SERVICE_TAG = 'contena.payment.gateway';

    public function code(): string;
}
