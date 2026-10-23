<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Webhook;

use Contena\Core\Content\Media\File\TrustedUrlResolver;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\Validation\WebhookTargetValidator;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class StaticWebhookTargetValidatorFactory
{
    /**
     * @param list<string> $allowedPrivateIpAddresses
     */
    public static function create(bool $allowUnencryptedTraffic, array $allowedPrivateIpAddresses, bool $enableUrlValidation = true): WebhookTargetValidator
    {
        return new WebhookTargetValidator($allowUnencryptedTraffic, $allowedPrivateIpAddresses, new TrustedUrlResolver(static fn (string $host): array => ['93.184.216.34'], allowedPrivateIps: $allowedPrivateIpAddresses), $enableUrlValidation);
    }
}
