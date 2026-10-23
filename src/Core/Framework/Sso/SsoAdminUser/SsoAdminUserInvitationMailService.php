<?php declare(strict_types=1);

namespace Contena\Core\Framework\Sso\SsoAdminUser;

use Contena\Core\Content\Mail\Service\AbstractMailService;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Sso\SsoException;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
class SsoAdminUserInvitationMailService
{
    private const ADMIN_ROUTE_NAME = 'administration.index';

    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     * @param EntityRepository<MailTemplateTypeCollection> $mailTemplateTypeRepository
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $mailTemplateTypeRepository,
        private readonly EntityRepository $adminUserRepository,
        private readonly EntityRepository $languageRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $appUrl
    ) {
    }

    public function sendInvitationMailToUser(string $recipientEmail, string $localeId, Context $context): void
    {
        $apiSource = $context->getSource();
        if (!$apiSource instanceof AdminApiSource) {
            return;
        }

        $adminUser = $this->getUserById($apiSource->getUserId(), $context);
        $shopName = $this->systemConfigService->getString('core.basicInformation.shopName');
        $mailTemplate = $this->getMailTemplate($localeId, $context);

        $mailDataArray = [
            'templateId' => $mailTemplate?->getId(),
            'recipients' => [$recipientEmail => $recipientEmail],
            'senderName' => $shopName,
            'subject' => $mailTemplate?->getTranslation('subject'),
            'contentPlain' => $mailTemplate?->getTranslation('contentPlain'),
            'contentHtml' => $mailTemplate?->getTranslation('contentHtml'),
        ];

        $templateVariablesArray = [
            'nameOfInviter' => $this->createInviterName($adminUser),
            'storeName' => $shopName,
            'invitedEmailAddress' => $recipientEmail,
            'signupUrl' => $this->createSignUpUrl(),
        ];

        $this->mailService->send($mailDataArray, $context, $templateVariablesArray);
    }

    private function createSignUpUrl(): string
    {
        return $this->appUrl . $this->urlGenerator->generate(self::ADMIN_ROUTE_NAME);
    }

    private function getMailTemplate(string $localeId, Context $context): ?MailTemplateEntity
    {
        $language = $this->getLanguageForLocale($localeId, $context);
        if ($language) {
            $newContext = new Context(
                $context->getSource(),
                $context->getRuleIds(),
                $context->getCurrencyId(),
                array_values(array_filter([
                    $language->getId(),
                    $language->getParentId(),
                    Defaults::LANGUAGE_SYSTEM,
                ])),
                $context->getVersionId(),
                $context->getCurrencyFactor(),
                $context->considerInheritance(),
                $context->getTaxState(),
                $context->getRounding()
            );
        } else {
            $newContext = $context;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'admin_sso_user_invite'));

        $result = $this->mailTemplateTypeRepository->search($criteria, $newContext)->getEntities()->first();
        if (!$result instanceof MailTemplateTypeEntity) {
            throw SsoException::mailTemplateNotFound();
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                MultiFilter::CONNECTION_AND,
                [
                    new EqualsFilter('mailTemplateTypeId', $result->getId()),
                    new EqualsFilter('systemDefault', true),
                ]
            )
        );

        return $this->mailTemplateRepository->search($criteria, $newContext)->getEntities()->first();
    }

    private function createInviterName(?AdminUserEntity $adminUser): string
    {
        $name = $adminUser?->getName();
        $adminUserName = $adminUser?->getUsername();

        if ($name !== null && $name !== '') {
            return $name;
        }

        if ($adminUserName !== null && $adminUserName !== '') {
            return $adminUserName;
        }

        return 'Administrator';
    }

    private function getUserById(?string $adminUserId, Context $context): ?AdminUserEntity
    {
        if ($adminUserId === null) {
            return null;
        }

        return $this->admin_userRepository->search(new Criteria([$adminUserId]), $context)->getEntities()->first();
    }

    private function getLanguageForLocale(string $localeId, Context $context): ?LanguageEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('localeId', $localeId));

        return $this->languageRepository->search($criteria, $context)->getEntities()->first();
    }
}
