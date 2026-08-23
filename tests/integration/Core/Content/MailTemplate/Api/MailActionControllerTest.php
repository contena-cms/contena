<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\MailTemplate\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class MailActionControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testSendSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()
            ->request(
                'POST',
                '/api/_action/mail-template/send',
                [
                    'contentHtml' => $mailTemplate->getContentHtml(),
                    'contentPlain' => $mailTemplate->getContentPlain(),
                    'mailTemplateData' => ['customName' => 'Contena'],
                    'recipients' => ['d.dinh@contena.cn' => 'Duy'],
                    'senderEmail' => 'sender@contena.cn',
                    'senderName' => 'Contena',
                    'subject' => 'Hello {{ customName }}',
                    'testMode' => false,
                ],
            );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('size', $response);
    }

    public function testPreviewSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/preview',
            [
                'mailTemplateId' => $mailTemplate->getId(),
                'includeHeaderFooter' => true,
                'templateData' => [
                    'customName' => 'Contena',
                ],
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertSame('success', $response['subject']['type']);
        static::assertSame('Hello Contena', $response['subject']['content']);
        static::assertSame('success', $response['contentHtml']['type']);
        static::assertStringContainsString('Contena', $response['contentHtml']['content']);
    }

    public function testGetDataAndSendSuccess(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createSimpleMailTemplate($context);

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/get-data-and-send',
            [
                'mailTemplateId' => $mailTemplate->getId(),
                'templateData' => [
                    'customName' => 'Contena',
                ],
                'recipients' => ['d.dinh@contena.cn' => 'Duy'],
                'senderEmail' => 'sender@contena.cn',
                'testMode' => false,
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('size', $response);
        static::assertGreaterThan(0, $response['size']);
    }

    public function testSimulateSuccess(): void
    {
        TestUser::createNewTestUser(
            $this->getBrowser()->getContainer()->get(Connection::class),
            ['api_send_email']
        )->authorizeBrowser($this->getBrowser());

        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/simulate',
            [
                'templateParts' => [
                    'contentHtml' => '<p>{{ userRecovery.user.email }} {{ resetUrl }}</p>',
                ],
                'eventName' => 'user.recovery.request',
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertSame('success', $response['contentHtml']['type']);
        static::assertNotSame('', $response['contentHtml']['content']);
    }

    public function testSimulateRequiresSendEmailPrivilege(): void
    {
        $browser = $this->getBrowser();
        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['mail_template:read']
        )->authorizeBrowser($browser);

        $browser->request('POST', '/api/_action/mail-template/simulate');

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
    }

    public function testAvailableVariablesSuccess(): void
    {
        $this->getBrowser()->request(
            'POST',
            '/api/_action/mail-template/available-variables',
            [
                'eventName' => 'user.recovery.request',
                'parentVariablePath' => 'userRecovery',
            ],
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertContains('user', array_column($response, 'fieldName'));
    }

    private function createSimpleMailTemplate(Context $context): MailTemplateEntity
    {
        $typeCriteria = new Criteria();
        $typeCriteria->setLimit(1);

        /** @var EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = static::getContainer()->get('mail_template_type.repository');
        $mailTemplateType = $mailTemplateTypeRepository->search($typeCriteria, $context)->getEntities()->first();

        static::assertInstanceOf(MailTemplateTypeEntity::class, $mailTemplateType);

        $mailTemplateId = Uuid::randomHex();

        /** @var EntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $mailTemplateRepository->create([[
            'id' => $mailTemplateId,
            'mailTemplateTypeId' => $mailTemplateType->getId(),
            'subject' => 'Hello {{ customName }}',
            'senderName' => 'Contena',
            'contentHtml' => '<p>Hello {{ customName }}</p>',
            'contentPlain' => 'Hello {{ customName }}',
        ]], $context);

        $mailTemplate = $mailTemplateRepository->search(new Criteria([$mailTemplateId]), $context)->getEntities()->first();

        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        return $mailTemplate;
    }
}
