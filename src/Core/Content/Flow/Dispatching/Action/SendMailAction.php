<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Psr\Log\LoggerInterface;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Mail\Payload\MailPayload;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
class SendMailAction extends FlowAction implements DelayableAction
{
    final public const string ACTION_NAME = 'action.mail.send';

    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly MailTemplateSendService $mailTemplateSendService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly EntityRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getName(): string
    {
        return self::ACTION_NAME;
    }

    /**
     * @return list<class-string>
     */
    public function requirements(): array
    {
        return [MailAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $config = $flow->getConfig();
        $templateId = $config['mailTemplateId'] ?? null;
        $mailStruct = $flow->getData(MailAware::MAIL_STRUCT);
        if (!\is_string($templateId) || !$mailStruct instanceof MailRecipientStruct) {
            return;
        }

        $context = $flow->getContext();
        $templateCriteria = new Criteria([$templateId]);
        if ($context->hasGlobalTenantAccess()) {
            $templateCriteria->addFilter(new EqualsFilter('tenantId', null));
        }

        $template = $this->mailTemplateRepository->search($templateCriteria, $context)->getEntities()->first();
        if (!$template instanceof MailTemplateEntity) {
            return;
        }

        $recipients = $this->resolveRecipients($config['recipient'] ?? null, $mailStruct, $context);
        if ($recipients === []) {
            return;
        }

        try {
            $this->mailTemplateSendService->send(
                new MailPayload(
                    recipients: $recipients,
                    contentHtml: $template->getContentHtml(),
                    contentPlain: $template->getContentPlain(),
                    subject: $template->getSubject(),
                    senderName: $template->getSenderName(),
                    recipientsCc: $mailStruct->getCc(),
                    recipientsBcc: $mailStruct->getBcc(),
                ),
                $context,
                ['eventName' => $flow->getName(), ...$flow->data()],
                $template,
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Could not send flow mail.', [
                'exception' => $exception,
                'flowEvent' => $flow->getName(),
                'tenantId' => $context->getTenantId(),
            ]);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function resolveRecipients(mixed $config, MailRecipientStruct $mailStruct, Context $context): array
    {
        if (!\is_array($config)) {
            return $mailStruct->getRecipients();
        }

        if (($config['type'] ?? null) === 'custom') {
            return \is_array($config['data'] ?? null) ? $config['data'] : [];
        }

        if (($config['type'] ?? null) !== 'admin') {
            return $mailStruct->getRecipients();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('admin', true));
        $criteria->addFilter(new EqualsFilter('active', true));
        if ($context->hasGlobalTenantAccess()) {
            $criteria->addFilter(new EqualsFilter('tenantId', null));
        }

        $recipients = [];
        foreach ($this->userRepository->search($criteria, $context)->getEntities() as $user) {
            if ($user instanceof UserEntity) {
                $recipients[$user->getEmail()] = $user->getName();
            }
        }

        return $recipients;
    }
}
