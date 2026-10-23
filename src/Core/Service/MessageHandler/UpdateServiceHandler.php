<?php declare(strict_types=1);

namespace Contena\Core\Service\MessageHandler;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\Message\UpdateServiceMessage;
use Contena\Core\Service\ServiceLifecycle;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class UpdateServiceHandler
{
    public function __construct(private ServiceLifecycle $serviceLifecycle)
    {
    }

    public function __invoke(UpdateServiceMessage $updateServiceMessage): void
    {
        $context = Context::createDefaultContext();

        $this->serviceLifecycle->update($updateServiceMessage->name, $context);
        $this->serviceLifecycle->reevaluateInstalled($context);
    }
}
