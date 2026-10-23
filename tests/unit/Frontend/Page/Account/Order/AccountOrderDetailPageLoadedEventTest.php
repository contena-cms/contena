<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Account\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Account\Order\AccountOrderDetailPage;
use Contena\Frontend\Page\Account\Order\AccountOrderDetailPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AccountOrderDetailPageLoadedEvent::class)]
class AccountOrderDetailPageLoadedEventTest extends TestCase
{
    public function testConstructorThrowsWhenTheFeatureIsActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Class "Contena\Frontend\Page\Account\Order\AccountOrderDetailPageLoadedEvent" is deprecated and will be removed in v6.8.0.0.'
        ));

        new AccountOrderDetailPageLoadedEvent(new AccountOrderDetailPage(), Generator::generateChannelContext(), new Request());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGettersReturnTheConstructorArgumentsWhenTheFeatureIsInactive(): void
    {
        $page = new AccountOrderDetailPage();
        $channelContext = Generator::generateChannelContext();
        $request = new Request();

        $event = new AccountOrderDetailPageLoadedEvent($page, $channelContext, $request);

        static::assertSame($page, $event->getPage());
        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($channelContext->getContext(), $event->getContext());
        static::assertSame($request, $event->getRequest());
    }
}
