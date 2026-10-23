<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Staging\Handler;

use Contena\Core\Content\Mail\Service\MailSender;
use Contena\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
readonly class StagingMailHandler
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        if (!$event->disableMailDelivery) {
            return;
        }

        $this->systemConfigService->set(MailSender::DISABLE_MAIL_DELIVERY, true, null, true);

        $event->io->info('Disabled mail delivery.');
    }
}
