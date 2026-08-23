<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Mail\Service;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Mail\Service\AbstractMailFactory;
use Contena\Core\Content\Mail\Service\AbstractMailSender;
use Contena\Core\Content\Mail\Service\MailService;
use Contena\Core\Content\Mail\Telemetry\MailMetricsInstrumentor;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Contena\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;

/**
 * @internal
 */
#[CoversClass(MailService::class)]
class MailServiceTest extends TestCase
{
    /**
     * @var Stub&StringTemplateRenderer
     */
    private StringTemplateRenderer $templateRenderer;

    /**
     * @var MockObject&AbstractMailFactory
     */
    private AbstractMailFactory $mailFactory;

    /**
     * @var Stub&EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var Stub&LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Stub&AbstractMailSender
     */
    private AbstractMailSender $mailSender;

    /**
     * @var Stub&LanguageLocaleCodeProvider
     */
    private LanguageLocaleCodeProvider $languageLocaleCodeProvider;

    protected function setUp(): void
    {
        $this->mailFactory = $this->createMock(AbstractMailFactory::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->templateRenderer = static::createStub(StringTemplateRenderer::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->mailSender = static::createStub(AbstractMailSender::class);
        $this->languageLocaleCodeProvider = static::createStub(LanguageLocaleCodeProvider::class);
    }

    public function testSendMailSuccess(): void
    {
        $data = [
            'recipients' => ['recipient@contena.cn' => null],
            'senderName' => 'me',
            'senderEmail' => 'me@contena.cn',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
        ];

        $email = new Email()->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@contena.cn')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(4))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailTemplateRenderContextEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );
        $email = $this->createMailService(
            templateRenderer: $templateRenderer,
            eventDispatcher: $eventDispatcher
        )->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
    }

    public function testSendMailDispatchesMailSentEventWithRenderedSubject(): void
    {
        $data = [
            'recipients' => ['recipient@contena.cn' => null],
            'senderName' => 'me',
            'senderEmail' => 'me@contena.cn',
            'subject' => 'Your message {{ message.number }}',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
        ];

        $email = new Email()->subject('Your message 10001')
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@contena.cn')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);

        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->exactly(4))->method('render')->willReturnCallback(
            static fn (string $template) => $template === 'Your message {{ message.number }}' ? 'Your message 10001' : $template
        );

        $mailSentEvent = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(4))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$mailSentEvent) {
                if ($event instanceof MailSentEvent) {
                    $mailSentEvent = $event;
                }

                return $event;
            });

        $this->createMailService(
            templateRenderer: $templateRenderer,
            eventDispatcher: $eventDispatcher
        )->send($data, Context::createDefaultContext(), ['message' => ['number' => '10001']]);

        static::assertInstanceOf(MailSentEvent::class, $mailSentEvent);
        static::assertSame('Your message 10001', $mailSentEvent->getSubject());
    }

    public function testSendMailWithRenderingError(): void
    {
        $data = [
            'recipients' => ['recipient@contena.cn' => null],
            'senderName' => 'me',
            'senderEmail' => 'me@contena.cn',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
        ];

        $email = new Email()->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to($data['senderEmail'])
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->never())->method('create')->willReturn($email);
        $beforeValidateEvent = null;
        $mailErrorEvent = null;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(Level::Warning);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$beforeValidateEvent, &$mailErrorEvent) {
                if ($event instanceof MailBeforeValidateEvent) {
                    $beforeValidateEvent = $event;

                    return $event;
                }

                if ($event instanceof MailTemplateRenderContextEvent) {
                    return $event;
                }

                $mailErrorEvent = $event;

                return $event;
            });

        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->once())->method('render')->willThrowException(new \Exception('cannot render'));

        $email = $this->createMailService(
            templateRenderer: $templateRenderer,
            eventDispatcher: $eventDispatcher,
            logger: $logger
        )->send($data, Context::createDefaultContext());

        static::assertNull($email);
        static::assertNotNull($beforeValidateEvent);
        static::assertInstanceOf(MailErrorEvent::class, $mailErrorEvent);
        static::assertSame(Level::Warning, $mailErrorEvent->getLogLevel());
        static::assertNotNull($mailErrorEvent->getMessage());

        $message = 'Could not render Mail-Subject with error message: cannot render';

        static::assertSame($message, $mailErrorEvent->getMessage());
        static::assertSame('Test email', $mailErrorEvent->getTemplate());
        static::assertSame([], $mailErrorEvent->getTemplateData());
    }

    public function testSendMailWithoutSenderName(): void
    {
        $data = [
            'recipients' => ['recipient@contena.cn' => null],
            'subject' => 'Test email',
            'senderName' => null,
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(Level::Error);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(5))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailTemplateRenderContextEvent::class),
            static::isInstanceOf(MailErrorEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );

        $email = new Email()->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('test@contena.cn')
            ->from(new Address('test@contena.cn'));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);

        $email = $this->createMailService(
            eventDispatcher: $eventDispatcher,
            logger: $logger
        )->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
    }

    public function testMailSenderExceptionIsHandled(): void
    {
        $data = [
            'recipients' => ['recipient@contena.cn' => null],
            'senderName' => 'me',
            'senderEmail' => 'me@contena.cn',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
        ];

        $email = new Email()->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@contena.cn')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(Level::Error);

        $beforeValidateEvent = null;
        $mailErrorEvent = null;

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$beforeValidateEvent, &$mailErrorEvent) {
                if ($event instanceof MailBeforeValidateEvent) {
                    $beforeValidateEvent = $event;

                    return $event;
                }

                if ($event instanceof MailErrorEvent) {
                    $mailErrorEvent = $event;
                }

                return $event;
            });

        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailSender->expects($this->once())->method('send')->willThrowException(new \Exception('Mail sending failed'));

        $email = $this->createMailService(
            templateRenderer: $templateRenderer,
            mailSender: $mailSender,
            logger: $logger
        )->send($data, Context::createDefaultContext());

        static::assertNull($email);
        static::assertNotNull($beforeValidateEvent);
        static::assertInstanceOf(MailErrorEvent::class, $mailErrorEvent);
        static::assertSame(Level::Error, $mailErrorEvent->getLogLevel());
        static::assertNotNull($mailErrorEvent->getMessage());
        static::assertSame('Could not send mail with error message: Mail sending failed', $mailErrorEvent->getMessage());
        static::assertSame('Content html', $mailErrorEvent->getTemplate());
        static::assertEmpty($mailErrorEvent->getTemplateData());
    }

    public function testMailInTestModeHasNoEmptyHeaders(): void
    {
        $data = [
            'testMode' => true,
            'recipients' => ['recipient@contena.cn' => null],
            'senderName' => 'me',
            'senderEmail' => 'me@contena.cn',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
        ];

        $templateData = [
            'eventName' => 'content.item.updated',
        ];

        $email = new Email()->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@contena.cn')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(4))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailTemplateRenderContextEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );
        $languageLocaleCodeProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $languageLocaleCodeProvider->expects($this->once())->method('getLocaleForLanguageId')->willReturn('en-GB');

        $email = $this->createMailService(
            templateRenderer: $templateRenderer,
            eventDispatcher: $eventDispatcher,
            languageLocaleCodeProvider: $languageLocaleCodeProvider
        )->send($data, Context::createDefaultContext(), $templateData);

        static::assertInstanceOf(Email::class, $email);
        $headers = $email->getHeaders();
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $headers->get('X-Contena-Language-Id')?->getBody());
        static::assertSame('content.item.updated', $headers->get('X-Contena-Event-Name')?->getBody());

        // check that no header is empty (e.g., Amazon SES doesn't like that)
        foreach ($headers->all() as $header) {
            static::assertInstanceOf(HeaderInterface::class, $header);
            static::assertNotEmpty($header->getBodyAsString(), 'mail header ' . $header->getName() . ' should not be empty');
        }
    }

    private function createMailService(
        ?StringTemplateRenderer $templateRenderer = null,
        ?AbstractMailSender $mailSender = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
        ?LanguageLocaleCodeProvider $languageLocaleCodeProvider = null,
    ): MailService {
        $mailMetrics = static::createStub(MailMetricsInstrumentor::class);
        $mailMetrics->method('measureSend')->willReturnCallback(
            static fn (?string $eventName, \Closure $send) => $send()
        );

        return new MailService(
            static::createStub(DataValidator::class),
            $templateRenderer ?? $this->templateRenderer,
            $this->mailFactory,
            $mailSender ?? $this->mailSender,
            static::createStub(EntityRepository::class),
            static::createStub(SystemConfigService::class),
            $eventDispatcher ?? $this->eventDispatcher,
            $logger ?? $this->logger,
            $languageLocaleCodeProvider ?? $this->languageLocaleCodeProvider,
            new MailTemplateContentBuilder(static::createStub(EntityRepository::class)),
            $mailMetrics,
        );
    }
}
