<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

class AppDeletedEvent extends Event implements ContenaEvent, Hookable
{
    final public const NAME = 'app.deleted';

    public function __construct(
        private readonly string $appId,
        private readonly Context $context,
        private readonly bool $keepUserData = false
    ) {
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function keepUserData(): bool
    {
        return $this->keepUserData;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'keepUserData' => $this->keepUserData,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $appId === $this->getAppId();
    }
}
