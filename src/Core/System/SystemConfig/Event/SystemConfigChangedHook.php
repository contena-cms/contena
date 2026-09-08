<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use Contena\Core\Framework\Webhook\Hookable;

/**
 * @final
 */
class SystemConfigChangedHook implements Hookable
{
    public const string EVENT_NAME = 'app.config.changed';

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $appMapping
     */
    public function __construct(
        private readonly array $values,
        private readonly array $appMapping,
        public readonly ?string $channelId = null,
        public readonly bool $silent = false,
    ) {
    }

    public function getName(): string
    {
        return 'app.config.changed';
    }

    /**
     * @return array{changes: list<string>, channelId: string|null}
     */
    public function getWebhookPayload(?AppEntity $app = null): array
    {
        if ($app === null) {
            return [
                'changes' => array_keys($this->values),
                'channelId' => $this->channelId,
            ];
        }

        $values = [];

        foreach ($this->values as $key => $value) {
            if (str_starts_with($key, $app->getName() . '.')) {
                $values[] = $key;
            }
        }

        return [
            'changes' => $values,
            'channelId' => $this->channelId,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        // Needs basic system_config.read permission
        if (!$permissions->isAllowed('system_config', 'read')) {
            return false;
        }

        $appName = $this->appMapping[$appId] ?? null;

        // When app doesn't exist
        if ($appName === null) {
            return false;
        }

        foreach ($this->values as $k => $v) {
            if (str_starts_with($k, $appName . '.')) {
                return true;
            }
        }

        return false;
    }
}
