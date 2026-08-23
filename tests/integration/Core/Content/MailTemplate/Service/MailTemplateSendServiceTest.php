<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Mail\Payload\MailPayload;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
class MailTemplateSendServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MailTemplateSendService $mailTemplateSendService;

    private Context $context;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $mailTemplateRepository;

    protected function setUp(): void
    {
        $this->mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $this->mailTemplateSendService = static::getContainer()->get(MailTemplateSendService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGetTemplateDataAndSend(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $email = $this->mailTemplateSendService->getTemplateDataAndSend(
            new GetDataAndSendRequest(
                $mailTemplate,
                [],
                ['customName' => 'Contena'],
                new MailPayload(
                    recipients: ['test@example.com' => 'Test'],
                    contentHtml: $mailTemplate->getContentHtml(),
                    contentPlain: $mailTemplate->getContentPlain(),
                    subject: $mailTemplate->getSubject(),
                    senderName: $mailTemplate->getSenderName(),
                    senderEmail: 'sender@example.com',
                )
            ),
            $this->context
        );

        static::assertInstanceOf(Email::class, $email);
        static::assertSame('Hello Contena', $email->getSubject());
        static::assertSame('Contena', $email->getFrom()[0]->getName());
        static::assertSame('Test', $email->getTo()[0]->getName());
        static::assertSame('test@example.com', $email->getTo()[0]->getAddress());
        static::assertStringContainsString('Hello Contena', (string) $email->getTextBody());
        static::assertStringContainsString('<p>Hello Contena</p>', (string) $email->getHtmlBody());
    }

    private function createSimpleMailTemplate(): MailTemplateEntity
    {
        $typeCriteria = new Criteria();
        $typeCriteria->setLimit(1);

        /** @var EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository */
        $mailTemplateTypeRepository = static::getContainer()->get('mail_template_type.repository');
        $mailTemplateType = $mailTemplateTypeRepository->search($typeCriteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(MailTemplateTypeEntity::class, $mailTemplateType);

        $mailTemplateId = Uuid::randomHex();

        $this->mailTemplateRepository->create([[
            'id' => $mailTemplateId,
            'mailTemplateTypeId' => $mailTemplateType->getId(),
            'subject' => 'Hello {{ customName }}',
            'senderName' => 'Contena',
            'contentHtml' => '<p>Hello {{ customName }}</p>',
            'contentPlain' => 'Hello {{ customName }}',
        ]], $this->context);

        $mailTemplate = $this->mailTemplateRepository->search(
            new Criteria([$mailTemplateId]),
            $this->context
        )->getEntities()->first();

        static::assertInstanceOf(MailTemplateEntity::class, $mailTemplate);

        return $mailTemplate;
    }
}
