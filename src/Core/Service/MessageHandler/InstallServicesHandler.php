<?php declare(strict_types=1);

namespace Contena\Core\Service\MessageHandler;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\LifecycleManager;
use Contena\Core\Service\Message\InstallServicesMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final readonly class InstallServicesHandler
{
    public function __construct(private LifecycleManager $manager)
    {
    }

    public function __invoke(InstallServicesMessage $installServicesMessage): void
    {
        $this->manager->reconcile(Context::createDefaultContext());
    }
}
