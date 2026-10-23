<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\SystemConfig\Channel\ConfigCastTrait;

/**
 * Blog listing, search and review settings (core.listing).
 *
 * @codeCoverageIgnore
 */
final class ShopListingSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly int $blogsPerPage,
        public readonly bool $allowBuyInListing,
        public readonly bool $showReview,
        public readonly int $reviewsPerPage,
        public readonly bool $disableEmptyFilterOptions,
        public readonly int $markAsNew,
        public readonly bool $hideCloseoutBlogsWhenOutOfStock,
        public readonly bool $showVariantOptionInSearchSuggestionResult,
        public readonly bool $findBestVariant,
        public readonly bool $autoplayVideoInListing,
        public readonly string $beforeListPriceSnippetKey,
        public readonly string $afterListPriceSnippetKey,
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
            blogsPerPage: self::intValue($config, 'blogsPerPage'),
            allowBuyInListing: self::boolValue($config, 'allowBuyInListing'),
            showReview: self::boolValue($config, 'showReview'),
            reviewsPerPage: self::intValue($config, 'reviewsPerPage'),
            disableEmptyFilterOptions: self::boolValue($config, 'disableEmptyFilterOptions'),
            markAsNew: self::intValue($config, 'markAsNew'),
            hideCloseoutBlogsWhenOutOfStock: self::boolValue($config, 'hideCloseoutBlogsWhenOutOfStock'),
            showVariantOptionInSearchSuggestionResult: self::boolValue($config, 'showVariantOptionInSearchSuggestionResult'),
            findBestVariant: self::boolValue($config, 'findBestVariant'),
            autoplayVideoInListing: self::boolValue($config, 'autoplayVideoInListing'),
            beforeListPriceSnippetKey: self::stringValue($config, 'beforeListPriceSnippetKey'),
            afterListPriceSnippetKey: self::stringValue($config, 'afterListPriceSnippetKey'),
        );
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_listing';
    }
}
