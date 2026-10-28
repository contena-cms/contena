<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Extension\DeleteCustomerRouteExtension;
use Contena\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\NoContentResponse;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeleteCustomerRoute::class)]
class DeleteCustomerRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $context = Generator::generateSalesChannelContext();
        $customer = new CustomerEntity();
        $response = new NoContentResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('delete-customer-route.delete.pre', static function (DeleteCustomerRouteExtension $extension) use ($context, $customer, $response): void {
            static::assertSame(['context' => $context, 'customer' => $customer], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new DeleteCustomerRoute(
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->delete($context, $customer));
    }
}
