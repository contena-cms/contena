<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Mail\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Mail\Service\AbstractMailSender;
use Contena\Core\Content\Mail\Service\MailFactory;
use Contena\Core\Content\Mail\Service\MailService;
use Contena\Core\Content\Mail\Telemetry\MailMetricsInstrumentor;
use Contena\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Contena\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * @internal
 */
class MailServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testPluginsCanExtendMailData(): void
    {
        $renderer = clone static::getContainer()->get(StringTemplateRenderer::class);
        $property = new \ReflectionProperty(StringTemplateRenderer::class, 'twig');

        $twig = $property->getValue($renderer);
        \assert($twig instanceof Environment);
        $environment = new TestEnvironment($twig->getLoader());
        $property->setValue($renderer, $environment);

        $mailService = new MailService(
            static::getContainer()->get(DataValidator::class),
            $renderer,
            static::getContainer()->get(MailFactory::class),
            $this->createMock(AbstractMailSender::class),
            $this->createMock(EntityRepository::class),
            static::getContainer()->get(SystemConfigService::class),
            static::getContainer()->get('event_dispatcher'),
            $this->createMock(LoggerInterface::class),
            $this->createMock(LanguageLocaleCodeProvider::class),
            static::getContainer()->get(MailTemplateContentBuilder::class),
            static::getContainer()->get(MailMetricsInstrumentor::class),
        );
        $data = [
            'senderName' => 'Foo & Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'senderEmail' => 'sender@example.com',
            'contentHtml' => '<h1>Test</h1>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject & content',
        ];

        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            MailBeforeValidateEvent::class,
            static function (MailBeforeValidateEvent $event): void {
                $event->setTemplateData(
                    [...$event->getTemplateData(), ...['plugin-value' => true]]
                );
            }
        );

        $mailService->send($data, Context::createDefaultContext());

        static::assertArrayHasKey(0, $environment->getCalls());
        $first = $environment->getCalls()[0];
        static::assertArrayHasKey('data', $first);
        static::assertArrayHasKey('plugin-value', $first['data']);
    }

    /**
     * @return iterable<string, mixed[]>
     */
    public static function senderEmailDataProvider(): iterable
    {
        yield 'basic sender is used when no config or mail data sender exists' => ['basic@example.com', 'basic@example.com', null, null];
        yield 'configured sender is used when basic sender is missing' => ['config@example.com', null, 'config@example.com', null];
        yield 'basic sender has priority over configured sender' => ['basic@example.com', 'basic@example.com', 'config@example.com', null];
        yield 'mail data sender has priority over basic and configured sender' => ['data@example.com', 'basic@example.com', 'config@example.com', 'data@example.com'];
        yield 'mail data sender has priority over basic sender' => ['data@example.com', 'basic@example.com', null, 'data@example.com'];
        yield 'mail data sender has priority over configured sender' => ['data@example.com', null, 'config@example.com', 'data@example.com'];
    }

    #[DataProvider('senderEmailDataProvider')]
    public function testEmailSender(string $expected, ?string $basicInformationEmail = null, ?string $configSender = null, ?string $dataSenderEmail = null): void
    {
        static::getContainer()
            ->get(Connection::class)
            ->executeStatement('DELETE FROM system_config WHERE configuration_key  IN ("core.mailerSettings.senderAddress", "core.basicInformation.email")');

        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        if ($configSender !== null) {
            $systemConfig->set('core.mailerSettings.senderAddress', $configSender);
        }
        if ($basicInformationEmail !== null) {
            $systemConfig->set('core.basicInformation.email', $basicInformationEmail);
        }

        $languageLocaleProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider
            ->method('getLocaleForLanguageId')
            ->willReturn('en-GB');

        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailService = new MailService(
            static::getContainer()->get(DataValidator::class),
            static::getContainer()->get(StringTemplateRenderer::class),
            static::getContainer()->get(MailFactory::class),
            $mailSender,
            $this->createMock(EntityRepository::class),
            $systemConfig,
            $this->createMock(EventDispatcher::class),
            $this->createMock(LoggerInterface::class),
            $languageLocaleProvider,
            static::getContainer()->get(MailTemplateContentBuilder::class),
            static::getContainer()->get(MailMetricsInstrumentor::class),
        );

        $data = [
            'senderName' => 'Foo & Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'contentHtml' => '<h1>Test</h1>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject & content',
        ];
        if ($dataSenderEmail !== null) {
            $data['senderMail'] = $dataSenderEmail;
        }

        $mailSender->expects($this->once())
            ->method('send')
            ->with(static::callback(function (Email $mail) use ($expected, $data): bool {
                $from = $mail->getFrom();
                $this->assertSame($data['senderName'], $from[0]->getName());
                $this->assertSame($data['subject'], $mail->getSubject());
                $this->assertCount(1, $from);
                $this->assertSame($data['senderMail'] ?? $expected, $from[0]->getAddress());

                $this->assertSame('en-GB', $mail->getHeaders()->get('Content-Language')?->getBodyAsString());

                return true;
            }));
        $mailService->send($data, Context::createDefaultContext());
    }

    public function testTenantSenderOverridesPlatformSender(): void
    {
        $platformContext = Context::createDefaultContext();
        $tenantContext = $this->createTenantContext($this->createTenant('Tenant mail sender'));
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $systemConfig->set('core.basicInformation.email', 'platform@example.com', context: $platformContext);
        $systemConfig->set('core.basicInformation.email', 'tenant@example.com', context: $tenantContext);

        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailSender->expects($this->once())
            ->method('send')
            ->with(
                static::callback(static fn (Email $mail): bool => $mail->getFrom()[0]->getAddress() === 'tenant@example.com'),
                $tenantContext,
            );

        $languageLocaleProvider = static::createStub(LanguageLocaleCodeProvider::class);
        $languageLocaleProvider->method('getLocaleForLanguageId')->willReturn('en-GB');
        $mailService = new MailService(
            static::getContainer()->get(DataValidator::class),
            static::getContainer()->get(StringTemplateRenderer::class),
            static::getContainer()->get(MailFactory::class),
            $mailSender,
            static::createStub(EntityRepository::class),
            $systemConfig,
            static::createStub(EventDispatcher::class),
            static::createStub(LoggerInterface::class),
            $languageLocaleProvider,
            static::getContainer()->get(MailTemplateContentBuilder::class),
            static::getContainer()->get(MailMetricsInstrumentor::class),
        );

        $mailService->send([
            'senderName' => 'Tenant sender',
            'recipients' => ['recipient@example.com' => 'Recipient'],
            'contentHtml' => '<h1>Tenant mail</h1>',
            'contentPlain' => 'Tenant mail',
            'subject' => 'Tenant mail',
        ], $tenantContext);
    }

    public function testItAllowsManipulationOfDataInBeforeValidateEvent(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(MailBeforeValidateEvent::class, static function (MailBeforeValidateEvent $event): void {
            $data = $event->getData();
            $data['senderEmail'] = 'test@email.com';

            $event->setData($data);
        });
        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailService = new MailService(
            static::getContainer()->get(DataValidator::class),
            $this->createMock(StringTemplateRenderer::class),
            static::getContainer()->get(MailFactory::class),
            $mailSender,
            $this->createMock(EntityRepository::class),
            static::getContainer()->get(SystemConfigService::class),
            $eventDispatcher,
            $this->createMock(LoggerInterface::class),
            $this->createMock(LanguageLocaleCodeProvider::class),
            static::getContainer()->get(MailTemplateContentBuilder::class),
            static::getContainer()->get(MailMetricsInstrumentor::class),
        );

        $data = [
            'senderName' => 'Foo Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'contentHtml' => '<h1>Test</h1>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject',
        ];

        $mailSender->expects($this->once())
            ->method('send')
            ->with(static::callback(function (Email $mail): bool {
                $from = $mail->getFrom();
                $this->assertCount(1, $from);
                $this->assertSame('test@email.com', $from[0]->getAddress());

                return true;
            }));
        $mailService->send($data, Context::createDefaultContext());
    }

    public function testMailSendingInTestMode(): void
    {
        $mailSender = $this->createMock(AbstractMailSender::class);
        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $mailService = new MailService(
            $this->getContainer()->get(DataValidator::class),
            $templateRenderer,
            static::getContainer()->get(MailFactory::class),
            $mailSender,
            $this->createMock(EntityRepository::class),
            static::getContainer()->get(SystemConfigService::class),
            $this->createMock(EventDispatcher::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(LanguageLocaleCodeProvider::class),
            static::getContainer()->get(MailTemplateContentBuilder::class),
            static::getContainer()->get(MailMetricsInstrumentor::class),
        );

        $data = [
            'senderName' => 'Foo Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'senderEmail' => 'sender@example.com',
            'contentHtml' => '<span>Test</span>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject',
            'testMode' => true,
        ];

        $templateData = [
            'eventName' => 'content.item.updated',
        ];

        $context = Context::createDefaultContext();

        $mailSender->expects($this->once())
            ->method('send')
            ->with(static::callback(function (Email $mail) use ($context): bool {
                $from = $mail->getFrom();
                $this->assertCount(1, $from);

                $this->assertNotNull($mail->getHeaders()->get('X-Contena-Event-Name'));
                $this->assertNotNull($mail->getHeaders()->get('X-Contena-Language-Id'));

                $languageIdHeader = $mail->getHeaders()->get('X-Contena-Language-Id');
                $this->assertSame($context->getLanguageId(), $languageIdHeader->getBodyAsString());

                return true;
            }));
        $mailService->send($data, $context, $templateData);
    }

    public function testHtmlEscaping(): void
    {
        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailService = new MailService(
            static::getContainer()->get(DataValidator::class),
            static::getContainer()->get(StringTemplateRenderer::class),
            static::getContainer()->get(MailFactory::class),
            $mailSender,
            $this->createMock(EntityRepository::class),
            static::getContainer()->get(SystemConfigService::class),
            $this->createMock(EventDispatcher::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(LanguageLocaleCodeProvider::class),
            static::getContainer()->get(MailTemplateContentBuilder::class),
            static::getContainer()->get(MailMetricsInstrumentor::class),
        );

        $data = [
            'senderName' => 'Foo & Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'contentHtml' => '<a href="{{ url }}">{{ text }}</a>',
            'contentPlain' => '{{ text }} {{ url }}',
            'subject' => 'Test',
            'senderEmail' => 'test@example.com',
        ];

        $mail = $mailService->send($data, Context::createDefaultContext(), [
            'text' => '<foobar>',
            'url' => 'http://example.com/?foo&bar=baz',
        ]);

        static::assertInstanceOf(Email::class, $mail);
        $htmlBody = $mail->getHtmlBody();
        $textBody = $mail->getTextBody();
        static::assertIsString($htmlBody);
        static::assertIsString($textBody);
        static::assertStringContainsString('<a href="http://example.com/?foo&amp;bar=baz">&lt;foobar&gt;</a>', $htmlBody);
        static::assertStringContainsString('<foobar> http://example.com/?foo&bar=baz', $textBody);
    }
}

/**
 * @internal
 */
class TestEnvironment extends Environment
{
    /**
     * @var array<int, mixed[]>
     */
    private array $calls = [];

    /**
     * @param mixed[] $context
     */
    public function render($name, array $context = []): string
    {
        $this->calls[] = ['source' => $name, 'data' => $context];

        return parent::render($name, $context);
    }

    /**
     * @return array<int, mixed[]>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
