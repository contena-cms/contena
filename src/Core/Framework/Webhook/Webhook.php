<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;

/**
 * Simple DTO for internal use
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class Webhook
{
    public function __construct(
        public string $id,
        public string $webhookName,
        public string $eventName,
        public string $url,
        public bool $onlyLiveVersion,
        public ?string $appId,
        public ?string $appName,
        public ?string $appSourceType,
        public bool $appActive,
        public ?string $appVersion,
        public ?string $appSecret,
        public ?string $appAclRoleId,
    ) {
    }
}
