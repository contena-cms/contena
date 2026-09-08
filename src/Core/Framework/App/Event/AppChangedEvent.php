<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal only for use by the app-system
 *
 * @codeCoverageIgnore
 */
abstract class AppChangedEvent extends Event implements ContenaEvent, Hookable
{
    public function __construct(
        private readonly AppEntity $app,
        private readonly Context $context
    ) {
    }

    abstract public function getName(): string;

    public function getApp(): AppEntity
    {
        return $this->app;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $appId === $this->app->getId();
    }
}
