<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\MessageHandler;

use Contena\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Contena\Core\Framework\App\Message\RotateAppSecretMessage;
use Contena\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal only for use by the app-system
 */
#[AsMessageHandler]
final class RotateAppSecretHandler
{
    public function __construct(
        private readonly AppSecretRotationService $rotationService
    ) {
    }

    public function __invoke(RotateAppSecretMessage $message): void
    {
        $context = Context::createDefaultContext();

        $this->rotationService->rotateNow($message->getAppId(), $context, $message->getTrigger());
    }
}
