<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Random;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
trait CustomerTestTrait
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private function getLoggedInContextToken(string $customerId, string $salesChannelId = TestDefaults::SALES_CHANNEL): string
    {
        $token = Random::getAlphanumericString(32);
        static::getContainer()->get(SalesChannelContextPersister::class)->save(
            $token,
            [
                'customerId' => $customerId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $salesChannelId,
            $customerId
        );

        return $token;
    }
}
