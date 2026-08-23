<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * Site identity and meta defaults (core.basicInformation).
 *
 * @codeCoverageIgnore
 */
final class SiteGeneralSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly string $siteName,
        public readonly string $metaAuthor,
        public readonly string $metaRobots,
        public readonly bool $familyFriendly,
    ) {
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.basicInformation config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            siteName: self::stringValue($config, 'siteName'),
            metaAuthor: self::stringValue($config, 'metaAuthor'),
            metaRobots: self::stringValue($config, 'metaRobots'),
            familyFriendly: self::boolValue($config, 'familyFriendly'),
        );
    }

    public function getApiAlias(): string
    {
        return 'site_settings_general';
    }
}
