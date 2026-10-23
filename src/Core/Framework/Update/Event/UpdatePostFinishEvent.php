<?php declare(strict_types=1);

namespace Contena\Core\Framework\Update\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable;

class UpdatePostFinishEvent extends UpdateEvent implements Hookable
{
    public const EVENT_NAME = 'contena.updated';

    private string $postUpdateMessage = '';

    public function __construct(
        Context $context,
        private readonly string $oldVersion,
        private readonly string $newVersion
    ) {
        parent::__construct($context);
    }

    public function getOldVersion(): string
    {
        return $this->oldVersion;
    }

    public function getNewVersion(): string
    {
        return $this->newVersion;
    }

    public function getPostUpdateMessage(): string
    {
        return $this->postUpdateMessage;
    }

    public function appendPostUpdateMessage(string $postUpdateMessage): void
    {
        $this->postUpdateMessage .= $postUpdateMessage . \PHP_EOL;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'oldVersion' => $this->oldVersion,
            'newVersion' => $this->newVersion,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
