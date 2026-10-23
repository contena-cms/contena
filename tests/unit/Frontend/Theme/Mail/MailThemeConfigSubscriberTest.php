<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Mail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\Mail\MailThemeConfigSubscriber;
use Contena\Frontend\Theme\Mail\MailThemeIdLoader;

/**
 * @internal
 */
#[CoversClass(MailThemeConfigSubscriber::class)]
class MailThemeConfigSubscriberTest extends TestCase
{
    public function testAddsChannelContextAndThemeIdToMailTemplateData(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $channelContext = Generator::generateChannelContext();

        $mailThemeIdLoader = $this->createMock(MailThemeIdLoader::class);
        $mailThemeIdLoader
            ->expects($this->once())
            ->method('load')
            ->with(TestDefaults::CHANNEL)
            ->willReturn($themeId);

        $contextFactory = $this->createMock(AbstractChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static fn (string $token): bool => Uuid::isValid($token)),
                TestDefaults::CHANNEL,
                [
                    ChannelContextService::LANGUAGE_ID => $context->getLanguageId(),
                    ChannelContextService::CURRENCY_ID => $context->getCurrencyId(),
                ],
            )
            ->willReturn($channelContext);

        $channel = new ChannelEntity();
        $channel->setId(TestDefaults::CHANNEL);

        $event = new MailTemplateRenderContextEvent([], $context, $channel);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $mailThemeIdLoader);
        $subscriber->addChannelContext($event);

        static::assertSame($channelContext, $event->getTemplateData()['channelContext']);
        static::assertSame($themeId, $event->getTemplateData()['themeId']);
    }

    public function testKeepsTemplateDataWhenSimulatedChannelHasNoContextData(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();

        $mailThemeIdLoader = $this->createMock(MailThemeIdLoader::class);
        $mailThemeIdLoader
            ->expects($this->once())
            ->method('load')
            ->with($channelId)
            ->willReturn(null);

        $contextFactory = $this->createMock(AbstractChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->willThrowException(ChannelException::noContextData($channelId));

        $channel = new ChannelEntity();
        $channel->setId($channelId);

        $event = new MailTemplateRenderContextEvent(['existing' => 'data'], $context, $channel);

        $subscriber = new MailThemeConfigSubscriber($contextFactory, $mailThemeIdLoader);
        $subscriber->addChannelContext($event);

        static::assertSame(['existing' => 'data'], $event->getTemplateData());
    }
}
