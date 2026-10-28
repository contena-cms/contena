<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits\Promotion;

use Contena\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

/**
 * @internal
 */
#[Package('checkout')]
trait PromotionIntegrationTestBehaviour
{
    private SalesChannelContext $context;

    /**
     * Gets a faked sales channel context
     * for the unit tests.
     */
    public function getContext(): SalesChannelContext
    {
        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        return $this->context;
    }




    /**
     * Gets all promotion codes that have been added
     * to the current session.
     *
     * @return array<mixed>
     */
    public function getSessionCodes(): array
    {
        $mockFileSessionStorage = static::getContainer()->get('session.storage.mock_file');
        static::assertInstanceOf(SessionStorageInterface::class, $mockFileSessionStorage);
        $session = new Session($mockFileSessionStorage);

        if (!$session->has(StorefrontCartSubscriber::SESSION_KEY_PROMOTION_CODES)) {
            return [];
        }

        return $session->get(StorefrontCartSubscriber::SESSION_KEY_PROMOTION_CODES);
    }
}
