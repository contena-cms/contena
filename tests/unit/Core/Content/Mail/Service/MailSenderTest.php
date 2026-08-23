<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Mail\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Mail\MailException;
use Contena\Core\Content\Mail\Message\SendMailMessage;
use Contena\Core\Content\Mail\Service\MailSender;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\TextPart;

/**
 * @internal
 */
#[CoversClass(MailSender::class)]
class MailSenderTest extends TestCase
{
    public function testSendMail(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $messageBus = static::createStub(MessageBusInterface::class);
        $fileSystem = static::createStub(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getBool')->with('core.staging')->willReturn(false);
        $configService->expects($this->once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender(
            $mailer,
            $fileSystem,
            $configService,
            0,
            static::createStub(LoggerInterface::class),
            0,
            $messageBus,
            false,
        );
        $mail = new Email();

        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($mail);

        $mailSender->send($mail, Context::createDefaultContext());
    }

    public function testSendMailWithoutMessageBus(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $fileSystem = static::createStub(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getBool')->with('core.staging')->willReturn(false);
        $configService->expects($this->once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender(
            $mailer,
            $fileSystem,
            $configService,
            0,
            static::createStub(LoggerInterface::class),
            0,
            null,
            false,
        );
        $mail = new Email();

        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($mail);

        $mailSender->send($mail, Context::createDefaultContext());
    }

    public function testSendLargeMail(): void
    {
        $mailer = static::createStub(MailerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $context = Context::createTenantContext(Uuid::randomHex());
        $configService->expects($this->once())->method('getBool')->with('core.staging', null, $context)->willReturn(false);
        $configService->expects($this->once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY, null, $context)->willReturn(false);
        $configService->expects($this->once())->method('getString')->with('core.mailerSettings.deliveryAddress', null, $context)->willReturn('');
        $maxMessageSizeKiB = 1024;
        $mailSender = new MailSender(
            $mailer,
            $fileSystem,
            $configService,
            0,
            static::createStub(LoggerInterface::class),
            $maxMessageSizeKiB,
            $messageBus,
            false,
        );
        static::assertIsInt($maxMessageSizeKiB);
        $text = str_repeat('a', $maxMessageSizeKiB * 1024);
        $mail = new Email(null, new TextPart($text));

        $testStruct = new ArrayStruct();

        $fileSystem
            ->expects($this->once())
            ->method('write')
            ->willReturnCallback(static function ($path, $content) use ($mail, $testStruct, $context): void {
                static::assertStringStartsWith('mail-data/' . $context->getTenantId() . '/', $path);
                static::assertSame(serialize($mail), $content);
                $testStruct->set('mailDataPath', $path);
            });

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($message) use ($testStruct, $context): Envelope {
                static::assertInstanceOf(SendMailMessage::class, $message);
                static::assertSame($testStruct->get('mailDataPath'), $message->mailDataPath);
                static::assertSame($context->getTenantId(), $message->tenantId);

                return new Envelope($message);
            });

        $mailSender->send($mail, $context);
    }

    public function testSendMailWithDisabledDelivery(): void
    {
        $mailer = static::createStub(MailerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getBool')->with('core.staging')->willReturn(false);
        $configService->expects($this->once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $mailSender = new MailSender($mailer, $fileSystem, $configService, 0, $logger, 0, $messageBus, false);
        $mail = new Email();

        $logger->expects($this->once())
            ->method('info');

        $fileSystem
            ->expects($this->never())
            ->method('write');

        $messageBus
            ->expects($this->never())
            ->method('dispatch');

        $mailSender->send($mail, Context::createDefaultContext());
    }

    public function testSendMailWithDisabledDeliveryInStagingMode(): void
    {
        $mailer = static::createStub(MailerInterface::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $fileSystem = $this->createMock(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getBool')->with('core.staging')->willReturn(true);
        $logger = $this->createMock(LoggerInterface::class);
        $mailSender = new MailSender($mailer, $fileSystem, $configService, 0, $logger, 0, $messageBus, true);
        $mail = new Email();

        $logger->expects($this->once())
            ->method('info');

        $fileSystem
            ->expects($this->never())
            ->method('write');

        $messageBus
            ->expects($this->never())
            ->method('dispatch');

        $mailSender->send($mail, Context::createDefaultContext());
    }

    public function testSendMailWithEnabledDeliveryInStagingMode(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $messageBus = static::createStub(MessageBusInterface::class);
        $fileSystem = static::createStub(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getBool')->with('core.staging')->willReturn(false);
        $configService->expects($this->once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender(
            $mailer,
            $fileSystem,
            $configService,
            0,
            static::createStub(LoggerInterface::class),
            0,
            $messageBus,
            false,
        );
        $mail = new Email();

        $mailer
            ->expects($this->once())
            ->method('send')
            ->with($mail);

        $mailSender->send($mail, Context::createDefaultContext());
    }

    public function testSendMailWithToMuchContent(): void
    {
        $mailer = static::createStub(MailerInterface::class);
        $messageBus = static::createStub(MessageBusInterface::class);
        $fileSystem = static::createStub(FilesystemOperator::class);
        $configService = $this->createMock(SystemConfigService::class);
        $configService->expects($this->once())->method('getBool')->with('core.staging')->willReturn(false);
        $configService->expects($this->once())->method('get')->with(MailSender::DISABLE_MAIL_DELIVERY)->willReturn(false);
        $mailSender = new MailSender(
            $mailer,
            $fileSystem,
            $configService,
            5,
            static::createStub(LoggerInterface::class),
            0,
            $messageBus,
            false,
        );

        $mail = new Email();
        $mail->text('foobar');

        $this->expectExceptionObject(MailException::mailBodyTooLong(5));

        $mailSender->send($mail, Context::createDefaultContext());
    }
}
