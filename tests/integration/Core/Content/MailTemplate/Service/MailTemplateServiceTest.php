<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Request\PreviewRequest;
use Contena\Core\Content\MailTemplate\Request\SimulateRequest;
use Contena\Core\Content\MailTemplate\Service\MailTemplateService;
use Contena\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MailTemplateServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private MailTemplateService $mailTemplateService;

    private Context $context;

    /**
     * @var EntityRepository<MailTemplateCollection>
     */
    private EntityRepository $mailTemplateRepository;

    protected function setUp(): void
    {
        $this->mailTemplateRepository = static::getContainer()->get('mail_template.repository');
        $this->mailTemplateService = static::getContainer()->get(MailTemplateService::class);
        $this->context = Context::createDefaultContext();
    }

    public function testLoadTemplate(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $loadedTemplate = $this->mailTemplateService->loadTemplate($mailTemplate->getId(), $this->context);

        static::assertSame($mailTemplate->getId(), $loadedTemplate->getId());
        static::assertSame('Hello {{ customName }}', $loadedTemplate->getSubject());
        static::assertSame('<p>Hello {{ customName }}</p>', $loadedTemplate->getContentHtml());
        static::assertSame('Hello {{ customName }}', $loadedTemplate->getContentPlain());
        static::assertSame('Contena', $loadedTemplate->getSenderName());
    }

    public function testPreviewRendersTemplateData(): void
    {
        $mailTemplate = $this->createSimpleMailTemplate();

        $rendered = $this->mailTemplateService->preview(
            new PreviewRequest(
                mailTemplate: $mailTemplate,
                entityMapping: [],
                templateData: ['customName' => 'Contena'],
            ),
            $this->context
        );

        static::assertEquals(MailTemplateRenderResult::success('Hello Contena'), $rendered['subject']);
        static::assertEquals(MailTemplateRenderResult::success('Contena'), $rendered['senderName']);
        static::assertEquals(MailTemplateRenderResult::success('<p>Hello Contena</p>'), $rendered['contentHtml']);
        static::assertEquals(MailTemplateRenderResult::success('Hello Contena'), $rendered['contentPlain']);
    }

    public function testSimulate(): void
    {
        $rendered = $this->mailTemplateService->simulate(
            new SimulateRequest(
                templateParts: ['contentHtml' => '<p>{{ userRecovery.user.email }} {{ resetUrl }}</p>'],
                eventName: 'user.recovery.request',
            ),
            $this->context
        );

        static::assertInstanceOf(MailTemplateRenderResult::class, $rendered['contentHtml']);
        static::assertSame(MailTemplateRenderResult::TYPE_SUCCESS, $rendered['contentHtml']->getType());
        static::assertNotSame('', $rendered['contentHtml']->getContent());
    }

    public function testGetAvailableVariables(): void
    {
        $variables = $this->mailTemplateService->getAvailableVariables('user.recovery.request', $this->context, 'userRecovery');

        static::assertIsArray($variables);
        static::assertNotSame([], $variables);
        static::assertContains('user', array_column($variables, 'fieldName'));
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
