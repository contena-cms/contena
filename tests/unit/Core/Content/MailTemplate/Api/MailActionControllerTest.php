<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Payload\MailPayload;
use Contena\Core\Content\Mail\Payload\MailPayloadFactory;
use Contena\Core\Content\MailTemplate\Api\MailActionController;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Contena\Core\Content\MailTemplate\Request\PreviewRequest;
use Contena\Core\Content\MailTemplate\Request\SimulateRequest;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Content\MailTemplate\Service\MailTemplateService;
use Contena\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailActionController::class)]
class MailActionControllerTest extends TestCase
{
    private MailTemplateService&MockObject $mailTemplateService;

    private MailTemplateSendService&MockObject $mailTemplateSendService;

    private MailPayloadFactory&MockObject $mailPayloadFactory;

    protected function setUp(): void
    {
        $this->mailTemplateService = $this->createMock(MailTemplateService::class);
        $this->mailTemplateSendService = $this->createMock(MailTemplateSendService::class);
        $this->mailPayloadFactory = $this->createMock(MailPayloadFactory::class);
    }

    public function testSendSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = new MailTemplateEntity();
        $mailPayload = new MailPayload(subject: 'subject');
        $data = new RequestDataBag([
            'mailTemplateId' => 'template-id',
            'mailTemplateData' => [
                'user' => [
                    'id' => 'user-id',
                ],
            ],
        ]);

        $this->mailPayloadFactory->expects($this->once())
            ->method('make')
            ->with($data)
            ->willReturn($mailPayload);

        $this->mailTemplateService->expects($this->once())
            ->method('loadTemplate')
            ->with('template-id', $context)
            ->willReturn($mailTemplate);

        $this->mailTemplateSendService->expects($this->once())
            ->method('send')
            ->with($mailPayload, $context, ['user' => ['id' => 'user-id']], $mailTemplate)
            ->willReturn($this->createEmail());

        $response = $this->createController()->send($data, $context);

        static::assertGreaterThan(0, $this->decodeResponse($response)['size']);
    }

    public function testSendWithoutTemplateIdNormalizesInvalidTemplateData(): void
    {
        $context = Context::createDefaultContext();
        $mailPayload = new MailPayload();
        $data = new RequestDataBag([
            'mailTemplateData' => 'invalid',
        ]);

        $this->mailPayloadFactory->expects($this->once())
            ->method('make')
            ->with($data)
            ->willReturn($mailPayload);

        $this->mailTemplateService->expects($this->never())
            ->method('loadTemplate');

        $this->mailTemplateSendService->expects($this->once())
            ->method('send')
            ->with($mailPayload, $context, [], null)
            ->willReturn(null);

        $response = $this->createController()->send($data, $context);

        static::assertSame('{"size":0}', $response->getContent());
    }

    public function testSimulate(): void
    {
        $context = Context::createDefaultContext();
        $simulateRequest = new SimulateRequest(
            templateParts: ['contentHtml' => 'Hello {{ email }}'],
            eventName: 'user.recovery.request',
            strictRendering: true,
        );

        $result = [
            'contentHtml' => MailTemplateRenderResult::success('Hello test@example.com'),
        ];

        $this->mailTemplateService->expects($this->once())
            ->method('simulate')
            ->with($simulateRequest, $context)
            ->willReturn($result);

        $response = $this->createController()->simulate($simulateRequest, $context);

        static::assertSame(
            [
                'contentHtml' => [
                    'type' => 'success',
                    'content' => 'Hello test@example.com',
                ],
            ],
            $this->decodeResponse($response)
        );
    }

    public function testPreview(): void
    {
        $context = Context::createDefaultContext();
        $previewRequest = new PreviewRequest(new MailTemplateEntity(), strictRendering: false);

        $result = [
            'subject' => MailTemplateRenderResult::success('Subject'),
        ];

        $this->mailTemplateService->expects($this->once())
            ->method('preview')
            ->with($previewRequest, $context)
            ->willReturn($result);

        $response = $this->createController()->preview($previewRequest, $context);

        static::assertSame(
            [
                'subject' => [
                    'type' => 'success',
                    'content' => 'Subject',
                ],
            ],
            $this->decodeResponse($response)
        );
    }

    public function testGetDataAndSend(): void
    {
        $context = Context::createDefaultContext();
        $request = new GetDataAndSendRequest(new MailTemplateEntity());

        $this->mailTemplateSendService->expects($this->once())
            ->method('getTemplateDataAndSend')
            ->with($request, $context)
            ->willReturn($this->createEmail());

        $response = $this->createController()->getDataAndSend($request, $context);

        static::assertGreaterThan(0, $this->decodeResponse($response)['size']);
    }

    public function testAvailableVariables(): void
    {
        $context = Context::createDefaultContext();
        $request = new RequestDataBag([
            'eventName' => 'user.recovery.request',
            'parentVariablePath' => 'user',
        ]);

        $this->mailTemplateService->expects($this->once())
            ->method('getAvailableVariables')
            ->with('user.recovery.request', $context, 'user')
            ->willReturn([['fieldName' => 'email', 'hasChildren' => false]]);

        $response = $this->createController()->availableVariables($request, $context);

        static::assertSame('[{"fieldName":"email","hasChildren":false}]', $response->getContent());
    }

    private function createController(): MailActionController
    {
        return new MailActionController(
            $this->mailTemplateService,
            $this->mailTemplateSendService,
            $this->mailPayloadFactory,
        );
    }

    private function createEmail(): Email
    {
        return new Email()
            ->from('sender@example.com')
            ->to('recipient@example.com')
            ->text('sent');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(object $response): array
    {
        \assert(method_exists($response, 'getContent'));

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
