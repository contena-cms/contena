<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Validation;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class WebhookTarget
{
    public function __construct(
        public string $host,
        public int $port,
        public ?string $ip,
    ) {
    }
}
