<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\System\SalesChannel\SalesChannelException;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Contena\Storefront\Theme\Mail\MailThemeConfigSubscriber;
use Contena\Storefront\Theme\Mail\MailThemeIdLoader;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MailThemeConfigSubscriber::class)]
class MailThemeConfigSubscriberTest extends TestCase
{
    public function testAddsSalesChannelContextAndThemeIdToMailTemplateData(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $mailThemeIdLoader = $this->createMock(MailThemeIdLoader::class);
        $mailThemeIdLoader
            ->expects($this->once())
            ->method('load')
            ->with(TestDefaults::SALES_CHANNEL)
            ->willReturn($themeId);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static fn (string $token): bool => Uuid::isValid($token)),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
                    SalesChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
                ],
            )
            ->willReturn($salesChannelContext);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);

        $event = new MailTemplateRenderContextEvent([], $context, $salesChannel);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $mailThemeIdLoader);
        $subscriber->addSalesChannelContext($event);

        static::assertSame($salesChannelContext, $event->getTemplateData()['salesChannelContext']);
        static::assertSame($themeId, $event->getTemplateData()['themeId']);
    }

    public function testKeepsTemplateDataWhenSimulatedSalesChannelHasNoContextData(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();

        $mailThemeIdLoader = $this->createMock(MailThemeIdLoader::class);
        $mailThemeIdLoader
            ->expects($this->once())
            ->method('load')
            ->with($salesChannelId)
            ->willReturn(null);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->willThrowException(SalesChannelException::noContextData($salesChannelId));

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $event = new MailTemplateRenderContextEvent(['existing' => 'data'], $context, $salesChannel);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $mailThemeIdLoader);
        $subscriber->addSalesChannelContext($event);

        static::assertSame(['existing' => 'data'], $event->getTemplateData());
    }
}
