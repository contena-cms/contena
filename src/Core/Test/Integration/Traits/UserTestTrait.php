<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits;

use Contena\Core\Framework\Util\Random;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
trait UserTestTrait
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private function getLoggedInContextToken(string $userId, string $channelId = TestDefaults::CHANNEL): string
    {
        $token = Random::getAlphanumericString(32);
        static::getContainer()->get(ChannelContextPersister::class)->save(
            $token,
            [
                'userId' => $userId,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            $channelId,
            $userId
        );

        return $token;
    }
}
