<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Payload\MailPayload;
use Contena\Core\Content\Mail\Service\AbstractMailService;
use Contena\Core\Content\Mail\Service\MailAttachmentsConfig;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Contena\Core\Content\MailTemplate\Service\MailDataProvider;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Framework\Context;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailTemplateSendService::class)]
class MailTemplateSendServiceTest extends TestCase
{
    private AbstractMailService&MockObject $mailService;

    private MailDataProvider&MockObject $mailDataProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailService = $this->createMock(AbstractMailService::class);
        $this->mailDataProvider = $this->createMock(MailDataProvider::class);
    }

    public function testGetTemplateDataAndSendUsesProviderDataAndTemplateForAttachments(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $mailPayload = new MailPayload(
            recipients: ['test@example.com' => 'Test'],
            subject: 'Subject',
            senderName: 'Contena',
            mediaIds: ['media-id']
        );
        $request = new GetDataAndSendRequest($mailTemplate, ['user' => 'user-id'], ['foo' => 'bar'], $mailPayload);

        $this->mailDataProvider->expects($this->once())
            ->method('getTemplateData')
            ->with($mailTemplate, ['user' => 'user-id'], $context, ['foo' => 'bar'])
            ->willReturn(['user' => ['id' => 'user-id']]);

        $this->mailService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(function (array $data) use ($mailTemplate): bool {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);
                    static::assertSame($mailTemplate, $data['attachmentsConfig']->getMailTemplate());
                    static::assertSame(['media-id'], $data['attachmentsConfig']->getExtension()->getMediaIds());

                    return true;
                }),
                $context,
                ['user' => ['id' => 'user-id']]
            )
            ->willReturn(null);

        $mailTemplateSendService = $this->createService();

        static::assertNull($mailTemplateSendService->getTemplateDataAndSend($request, $context));
    }

    public function testSendBuildsAttachmentsConfigWithoutMailTemplate(): void
    {
        $context = Context::createDefaultContext();
        $this->mailService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(function (array $data): bool {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);
                    static::assertSame([], $data['attachmentsConfig']->getExtension()->getMediaIds());

                    return true;
                }),
                $context,
                ['user' => ['id' => 'user-id']]
            )
            ->willReturn(static::createStub(Email::class));

        $mailTemplateSendService = $this->createService();

        $result = $mailTemplateSendService->send(
            new MailPayload(subject: 'Subject', senderName: 'Sender'),
            $context,
            ['user' => ['id' => 'user-id']]
        );

        static::assertInstanceOf(Email::class, $result);
    }

    private function createService(): MailTemplateSendService
    {
        return new MailTemplateSendService(
            $this->mailService,
            $this->mailDataProvider,
        );
    }
}
