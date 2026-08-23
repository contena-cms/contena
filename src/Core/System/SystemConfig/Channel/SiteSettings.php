<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * Channel-resolved subset of the system configuration that headless frontends
 * need to render the site consistently with the administration settings. Only
 * UI- and validation-relevant, non-sensitive values are exposed.
 *
 * @codeCoverageIgnore
 */
final class SiteSettings extends Struct
{
    /**
     * @internal
     */
    public function __construct(
        public readonly SiteGeneralSettings $general,
        public readonly SiteLoginRegistrationSettings $loginRegistration,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'site_settings';
    }
}
