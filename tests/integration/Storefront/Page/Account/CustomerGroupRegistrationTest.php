<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class CustomerGroupRegistrationTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelContext $salesChannel;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->salesChannel = $this->createSalesChannelContext();
    }

    public function test404(): void
    {
        static::expectException(CustomerException::class);
        $request = new Request();
        $request->attributes->set('customerGroupId', Defaults::LANGUAGE_SYSTEM);

        $this->getPageLoader()->load($request, $this->salesChannel);
    }

    public function testGetConfiguration(): void
    {
        $customerGroupRepository = static::getContainer()->get('customer_group.repository');
        $customerGroupRepository->create([
            [
                'id' => $this->ids->create('group'),
                'name' => 'foo',
                'registrationActive' => true,
                'registrationTitle' => 'test',
                'registrationSalesChannels' => [['id' => $this->salesChannel->getSalesChannelId()]],
            ],
        ], Context::createDefaultContext());

        $request = new Request();
        $request->attributes->set('customerGroupId', $this->ids->get('group'));

        $page = $this->getPageLoader()->load($request, $this->salesChannel);
        static::assertSame($this->ids->get('group'), $page->getGroup()->getId());
        static::assertSame('test', $page->getGroup()->getRegistrationTitle());
    }

}
