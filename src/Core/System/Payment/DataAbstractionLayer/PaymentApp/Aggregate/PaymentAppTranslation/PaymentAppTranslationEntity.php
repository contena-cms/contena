<?php declare(strict_types=1);

namespace Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\Aggregate\PaymentAppTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;

class PaymentAppTranslationEntity extends TranslationEntity
{
    use EntityCustomFieldsTrait;

    public protected(set) ?string $tenantId = null;

    public protected(set) string $paymentAppId;

    public protected(set) ?string $name = null;

    public protected(set) ?PaymentAppEntity $paymentApp = null;
}
