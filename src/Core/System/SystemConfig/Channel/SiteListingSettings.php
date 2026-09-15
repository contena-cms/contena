<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * Blog listing and comment settings (core.listing).
 *
 * @codeCoverageIgnore
 */
final class SiteListingSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly bool $buildBreadcrumbByReferrerCategory,
        public readonly bool $disableEmptyFilterOptions,
        public readonly int $blogsPerPage,
        public readonly bool $showComments,
        public readonly int $commentsPerPage,
    ) {
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.listing config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            buildBreadcrumbByReferrerCategory: self::boolValue($config, 'buildBreadcrumbByReferrerCategory'),
            disableEmptyFilterOptions: self::boolValue($config, 'disableEmptyFilterOptions'),
            blogsPerPage: self::intValue($config, 'blogsPerPage'),
            showComments: self::boolValue($config, 'showComments'),
            commentsPerPage: self::intValue($config, 'commentsPerPage'),
        );
    }

    public function getApiAlias(): string
    {
        return 'site_settings_listing';
    }
}
