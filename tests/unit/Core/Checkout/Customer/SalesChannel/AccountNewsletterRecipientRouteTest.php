<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Extension\AccountNewsletterRecipientRouteExtension;
use Contena\Core\Checkout\Customer\SalesChannel\AccountNewsletterRecipientRoute;
use Contena\Core\Checkout\Customer\SalesChannel\AccountNewsletterRecipientRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AccountNewsletterRecipientRoute::class)]
class AccountNewsletterRecipientRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $customer = new CustomerEntity();
        $response = static::createStub(AccountNewsletterRecipientRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('account-newsletter-recipient-route.load.pre', static function (AccountNewsletterRecipientRouteExtension $extension) use ($request, $context, $criteria, $customer, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new AccountNewsletterRecipientRoute(
            static::createStub(SalesChannelRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria, $customer));
    }
}
