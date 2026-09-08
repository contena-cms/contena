<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

class AppPermissionsUpdated extends Event implements ContenaEvent, Hookable
{
    final public const NAME = 'app.permissions.updated';

    /**
     * @param array<string> $permissions
     */
    public function __construct(
        public readonly string $appId,
        public readonly array $permissions,
        private readonly Context $context,
    ) {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'permissions' => $this->permissions,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $appId === $this->appId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
