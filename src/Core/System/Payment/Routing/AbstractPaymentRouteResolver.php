<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\Routing;

use Contena\Core\Framework\Context;
use Contena\Core\System\Payment\Rule\PaymentRuleScope;
use Contena\Core\System\Payment\Struct\PaymentRoute;

abstract class AbstractPaymentRouteResolver
{
    abstract public function getDecorated(): self;

    abstract public function resolve(PaymentRuleScope $scope): PaymentRoute;

    abstract public function resolveConfigured(string $channel, string $channelConfigId, string $operation, Context $context): PaymentRoute;
}
