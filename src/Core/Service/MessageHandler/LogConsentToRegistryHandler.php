<?php declare(strict_types=1);

namespace Contena\Core\Service\MessageHandler;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Service\Message\LogPermissionToRegistryMessage;
use Contena\Core\Service\ServiceRegistry\PermissionLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final class LogConsentToRegistryHandler
{
    public function __construct(private readonly PermissionLogger $logger)
    {
    }

    public function __invoke(LogPermissionToRegistryMessage $message): void
    {
        $this->logger->logSync($message->permissionsConsent, $message->consentState);
    }
}
