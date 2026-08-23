<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Subscriber;

use Contena\Core\Content\Mail\Payload\MailPayload;
use Contena\Core\Content\MailTemplate\MailTemplateCollection;
use Contena\Core\Content\MailTemplate\MailTemplateEntity;
use Contena\Core\Content\MailTemplate\MailTemplateTypes;
use Contena\Core\Content\MailTemplate\Service\MailTemplateSendService;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class UserRecoveryMailSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<MailTemplateCollection> $mailTemplateRepository
     */
    public function __construct(
        private readonly EntityRepository $mailTemplateRepository,
        private readonly MailTemplateSendService $mailTemplateSendService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [UserRecoveryRequestEvent::EVENT_NAME => 'sendRecoveryMail'];
    }

    public function sendRecoveryMail(UserRecoveryRequestEvent $event): void
    {
        $user = $event->getUserRecovery()->getUser();
        if ($user === null) {
            return;
        }

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('mailTemplateType.technicalName', MailTemplateTypes::MAILTYPE_USER_RECOVERY_REQUEST))
            ->addFilter(new EqualsFilter('systemDefault', true))
            ->addAssociation('mailTemplateType')
            ->addAssociation('media.media')
            ->setLimit(1);
        $criteria->setTitle('user-recovery::load-mail-template');

        $mailTemplate = $this->mailTemplateRepository->search($criteria, $event->getContext())->getEntities()->first();
        if (!$mailTemplate instanceof MailTemplateEntity) {
            return;
        }

        $this->mailTemplateSendService->send(
            new MailPayload(
                recipients: [$user->getEmail() => $user->getName()],
                contentHtml: $mailTemplate->getContentHtml(),
                contentPlain: $mailTemplate->getContentPlain(),
                subject: $mailTemplate->getSubject(),
                senderName: $mailTemplate->getSenderName(),
            ),
            $event->getContext(),
            [
                'userRecovery' => $event->getUserRecovery(),
                'resetUrl' => $event->getResetUrl(),
            ],
            $mailTemplate,
        );
    }
}
