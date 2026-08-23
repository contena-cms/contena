<?php declare(strict_types=1);

namespace Contena\Core\System\Consent\Definition;

use Contena\Core\System\Consent\ConsentDefinition;
use Contena\Core\System\Consent\ConsentScope;

/**
 * Consent of frontend visitors to the cookie banner.
 *
 * Revisions are tracked via the `cookie_consent_config_version` table
 * (one snapshot per cookie configuration hash), not through the consent
 * system's revision mechanism, hence getLatestRevision() returns null.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
class CookieConsent implements ConsentDefinition
{
    public const NAME = 'cookie_consent';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getScopeName(): string
    {
        return ConsentScope\FrontendVisitor::NAME;
    }

    public function getSince(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-07-13');
    }

    public function getRequiredPermissions(): array
    {
        return [];
    }

    public function getLatestRevision(): ?string
    {
        return null;
    }
}
