<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\ApiOrderCartService;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\CheckoutPermissions;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
class ApiOrderCartServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private ChannelContextPersister $contextPersister;

    private ChannelContext $channelContext;

    private ApiOrderCartService $adminOrderCartService;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $this->contextPersister = new ChannelContextPersister($this->connection, $eventDispatcher, static::getContainer()->get(CartPersister::class), new NativeClock());
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $this->adminOrderCartService = static::getContainer()->get(ApiOrderCartService::class);
    }

    public function testAddPermission(): void
    {
        $this->adminOrderCartService->addPermission($this->channelContext->getToken(), CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $this->channelContext->getChannelId());
        $payload = $this->contextPersister->load($this->channelContext->getToken(), $this->channelContext->getChannelId());
        static::assertArrayHasKey(CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $payload[ChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[ChannelContextService::PERMISSIONS][CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS]);
    }

    public function testAddMultiplePermissions(): void
    {
        $this->adminOrderCartService->addPermission($this->channelContext->getToken(), CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $this->channelContext->getChannelId());
        $this->adminOrderCartService->addPermission($this->channelContext->getToken(), CheckoutPermissions::SKIP_PROMOTION, $this->channelContext->getChannelId());
        $payload = $this->contextPersister->load($this->channelContext->getToken(), $this->channelContext->getChannelId());

        static::assertArrayHasKey(ChannelContextService::PERMISSIONS, $payload);
        static::assertCount(2, $payload[ChannelContextService::PERMISSIONS]);
        static::assertArrayHasKey(CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $payload[ChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[ChannelContextService::PERMISSIONS][CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS]);

        static::assertArrayHasKey(CheckoutPermissions::SKIP_PROMOTION, $payload[ChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[ChannelContextService::PERMISSIONS][CheckoutPermissions::SKIP_PROMOTION]);
    }

    public function testDeletePermission(): void
    {
        $this->adminOrderCartService->addPermission($this->channelContext->getToken(), CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $this->channelContext->getChannelId());
        $payload = $this->contextPersister->load($this->channelContext->getToken(), $this->channelContext->getChannelId());
        static::assertArrayHasKey(CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $payload[ChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[ChannelContextService::PERMISSIONS][CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS]);

        $this->adminOrderCartService->deletePermission($this->channelContext->getToken(), CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $this->channelContext->getChannelId());
        $payload = $this->contextPersister->load($this->channelContext->getToken(), $this->channelContext->getChannelId());
        static::assertArrayHasKey(CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $payload[ChannelContextService::PERMISSIONS]);
        static::assertFalse($payload[ChannelContextService::PERMISSIONS][CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS]);
    }
}
