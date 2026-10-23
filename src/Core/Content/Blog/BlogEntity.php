<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Contena\Core\System\User\Aggregate\UserWishlistBlog\UserWishlistBlogCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsCollection;
use Contena\Core\Content\Blog\Aggregate\BlogDownload\BlogDownloadCollection;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetEntity;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordCollection;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityCollection;
use Contena\Core\Content\Blog\DataAbstractionLayer\VariantListingConfig;
use Contena\Core\Content\ContentStream\ContentStreamCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Contena\Core\System\DeliveryTime\DeliveryTimeEntity;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\System\Unit\UnitEntity;
use Symfony\Component\Clock\Clock;

class BlogEntity extends Entity implements \Stringable
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $parentId = null;

    protected int $childCount = 0;

    protected int $autoIncrement;

    protected ?string $taxId = null;

    protected ?string $manufacturerId = null;

    protected ?string $unitId = null;

    protected ?bool $active = null;

    protected ?string $displayGroup = null;

    protected ?PriceCollection $price = null;

    protected ?string $manufacturerNumber = null;

    protected ?int $guaranteeMonths = null;

    protected ?bool $guaranteeConfirmed = null;

    protected ?string $guaranteeTermsMediaId = null;

    protected ?MediaEntity $guaranteeTermsMedia = null;

    protected ?string $guaranteeTermsUrl = null;

    protected ?string $ean = null;

    protected int $sales;

    protected string $blogNumber;

    protected int $stock;

    protected ?int $availableStock = null;

    protected bool $available;

    protected string $type = BlogDefinition::TYPE_PHYSICAL;

    protected ?string $deliveryTimeId = null;

    protected ?DeliveryTimeEntity $deliveryTime = null;

    protected ?int $restockTime = null;

    protected ?bool $isCloseout = null;

    protected ?int $purchaseSteps = null;

    protected ?int $maxPurchase = null;

    protected ?int $minPurchase = null;

    protected ?float $purchaseUnit = null;

    protected ?float $referenceUnit = null;

    protected ?bool $shippingFree = null;

    protected ?PriceCollection $purchasePrices = null;

    protected ?bool $markAsTopseller = null;

    protected ?float $weight = null;

    protected ?float $width = null;

    protected ?float $height = null;

    protected ?float $length = null;

    protected ?\DateTimeInterface $releaseDate = null;

    /**
     * @var array<string>|null
     */
    protected ?array $categoryTree = null;

    /**
     * @var array<string>|null
     */
    protected ?array $streamIds = null;

    /**
     * @var array<string>|null
     */
    protected ?array $optionIds = null;

    /**
     * @var array<string>|null
     */
    protected ?array $propertyIds = null;

    protected ?string $name = null;

    protected ?string $keywords = null;

    protected ?string $description = null;

    protected ?string $descriptionTeaser = null;

    protected ?string $metaDescription = null;

    protected ?string $metaTitle = null;

    protected ?string $ogTitle = null;

    protected ?string $ogDescription = null;

    protected ?string $packUnit = null;

    protected ?string $packUnitPlural = null;

    /**
     * @var array<string>|null
     */
    protected ?array $variantRestrictions = null;

    protected ?VariantListingConfig $variantListingConfig = null;

    /**
     * @var array<array<string>>
     */
    protected array $variation = [];

    protected ?TaxEntity $tax = null;

    protected ?BlogManufacturerEntity $manufacturer = null;

    protected ?UnitEntity $unit = null;

    protected BlogPriceCollection $prices;

    protected ?BlogMediaEntity $cover = null;

    protected ?BlogEntity $parent = null;

    protected ?BlogCollection $children = null;

    protected ?BlogMediaCollection $media = null;

    protected ?string $cmsPageId = null;

    protected ?CmsPageEntity $cmsPage = null;

    /**
     * @var array<string, array<string, array<string, mixed>>|null>|null
     */
    protected ?array $slotConfig = null;

    protected ?BlogSearchKeywordCollection $searchKeywords = null;

    protected ?BlogTranslationCollection $translations = null;

    protected ?CategoryCollection $categories = null;

    protected ?CustomFieldSetCollection $customFieldSets = null;

    protected ?TagCollection $tags = null;

    protected ?PropertyGroupOptionCollection $properties = null;

    protected ?PropertyGroupOptionCollection $options = null;

    protected ?BlogConfiguratorSettingCollection $configuratorSettings = null;

    protected ?CategoryCollection $categoriesRo = null;

    protected ?string $coverId = null;

    protected ?BlogVisibilityCollection $visibilities = null;

    /**
     * @var array<string>|null
     */
    protected ?array $tagIds = null;

    /**
     * @var array<string>|null
     */
    protected ?array $categoryIds = null;

    protected ?BlogReviewCollection $blogReviews = null;

    protected ?float $ratingAverage = null;

    protected ?MainCategoryCollection $mainCategories = null;

    protected ?SeoUrlCollection $seoUrls = null;

    protected ?OrderLineItemCollection $orderLineItems = null;

    protected ?BlogCrossSellingCollection $crossSellings = null;

    protected ?BlogCrossSellingAssignedBlogsCollection $crossSellingAssignedBlogs = null;

    protected ?string $featureSetId = null;

    protected ?BlogFeatureSetEntity $featureSet = null;

    protected ?bool $customFieldSetSelectionActive = null;

    /**
     * @var array<string>|null
     */
    protected ?array $customSearchKeywords = null;

    protected ?UserWishlistBlogCollection $wishlists = null;

    protected ?string $canonicalBlogId = null;

    protected ?BlogEntity $canonicalBlog = null;

    protected ?ContentStreamCollection $streams = null;

    protected ?BlogDownloadCollection $downloads = null;

    protected ?string $openGraphMediaId = null;

    protected ?MediaEntity $openGraphMedia = null;

    /**
     * @deprecated tag:v6.8.0 - Will be removed, please use type field instead.
     *
     * @var array<int, string>
     */
    protected array $states = [];

    public function __construct()
    {
        $this->prices = new BlogPriceCollection();
    }

    public function __toString(): string
    {
        return (string) ($this->getTranslation('name') ?? $this->getName());
    }

    public function getBlogReviews(): ?BlogReviewCollection
    {
        return $this->blogReviews;
    }

    public function setBlogReviews(BlogReviewCollection $blogReviews): void
    {
        $this->blogReviews = $blogReviews;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): void
    {
        $this->taxId = $taxId;
    }

    public function getManufacturerId(): ?string
    {
        return $this->manufacturerId;
    }

    public function setManufacturerId(?string $manufacturerId): void
    {
        $this->manufacturerId = $manufacturerId;
    }

    public function getUnitId(): ?string
    {
        return $this->unitId;
    }

    public function setUnitId(?string $unitId): void
    {
        $this->unitId = $unitId;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getPrice(): ?PriceCollection
    {
        return $this->price;
    }

    public function setPrice(PriceCollection $price): void
    {
        $this->price = $price;
    }

    public function getCurrencyPrice(string $currencyId): ?Price
    {
        return $this->price?->getCurrencyPrice($currencyId);
    }

    public function getManufacturerNumber(): ?string
    {
        return $this->manufacturerNumber;
    }

    public function setManufacturerNumber(?string $manufacturerNumber): void
    {
        $this->manufacturerNumber = $manufacturerNumber;
    }

    public function getGuaranteeMonths(): ?int
    {
        return $this->guaranteeMonths;
    }

    public function setGuaranteeMonths(?int $guaranteeMonths): void
    {
        $this->guaranteeMonths = $guaranteeMonths;
    }

    public function isGuaranteeConfirmed(): bool
    {
        return $this->guaranteeConfirmed ?? false;
    }

    public function setGuaranteeConfirmed(bool $guaranteeConfirmed): void
    {
        $this->guaranteeConfirmed = $guaranteeConfirmed;
    }

    public function getGuaranteeTermsMediaId(): ?string
    {
        return $this->guaranteeTermsMediaId;
    }

    public function setGuaranteeTermsMediaId(?string $guaranteeTermsMediaId): void
    {
        $this->guaranteeTermsMediaId = $guaranteeTermsMediaId;
    }

    public function getGuaranteeTermsMedia(): ?MediaEntity
    {
        return $this->guaranteeTermsMedia;
    }

    public function setGuaranteeTermsMedia(?MediaEntity $guaranteeTermsMedia): void
    {
        $this->guaranteeTermsMedia = $guaranteeTermsMedia;
    }

    public function getGuaranteeTermsUrl(): ?string
    {
        return $this->guaranteeTermsUrl;
    }

    public function setGuaranteeTermsUrl(?string $guaranteeTermsUrl): void
    {
        $this->guaranteeTermsUrl = $guaranteeTermsUrl;
    }

    public function getEan(): ?string
    {
        return $this->ean;
    }

    public function setEan(?string $ean): void
    {
        $this->ean = $ean;
    }

    public function getSales(): int
    {
        return $this->sales;
    }

    public function setSales(int $sales): void
    {
        $this->sales = $sales;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function getIsCloseout(): bool
    {
        return (bool) $this->isCloseout;
    }

    public function setIsCloseout(?bool $isCloseout): void
    {
        $this->isCloseout = $isCloseout;
    }

    public function getPurchaseSteps(): ?int
    {
        return $this->purchaseSteps;
    }

    public function setPurchaseSteps(?int $purchaseSteps): void
    {
        $this->purchaseSteps = $purchaseSteps;
    }

    public function getMaxPurchase(): ?int
    {
        return $this->maxPurchase;
    }

    public function setMaxPurchase(?int $maxPurchase): void
    {
        $this->maxPurchase = $maxPurchase;
    }

    public function getMinPurchase(): ?int
    {
        return $this->minPurchase;
    }

    public function setMinPurchase(?int $minPurchase): void
    {
        $this->minPurchase = $minPurchase;
    }

    public function getPurchaseUnit(): ?float
    {
        return $this->purchaseUnit;
    }

    public function setPurchaseUnit(?float $purchaseUnit): void
    {
        $this->purchaseUnit = $purchaseUnit;
    }

    public function getReferenceUnit(): ?float
    {
        return $this->referenceUnit;
    }

    public function setReferenceUnit(?float $referenceUnit): void
    {
        $this->referenceUnit = $referenceUnit;
    }

    public function getShippingFree(): ?bool
    {
        return $this->shippingFree;
    }

    public function setShippingFree(?bool $shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    public function getPurchasePrices(): ?PriceCollection
    {
        return $this->purchasePrices;
    }

    public function setPurchasePrices(?PriceCollection $purchasePrices): void
    {
        $this->purchasePrices = $purchasePrices;
    }

    public function getMarkAsTopseller(): ?bool
    {
        return $this->markAsTopseller;
    }

    public function setMarkAsTopseller(?bool $markAsTopseller): void
    {
        $this->markAsTopseller = $markAsTopseller;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function setWidth(?float $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function setHeight(?float $height): void
    {
        $this->height = $height;
    }

    public function getLength(): ?float
    {
        return $this->length;
    }

    public function setLength(?float $length): void
    {
        $this->length = $length;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeInterface $releaseDate): void
    {
        $this->releaseDate = $releaseDate;
    }

    /**
     * @return array<string>|null
     */
    public function getCategoryTree(): ?array
    {
        return $this->categoryTree;
    }

    /**
     * @param array<string>|null $categoryTree
     */
    public function setCategoryTree(?array $categoryTree): void
    {
        $this->categoryTree = $categoryTree;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getDescriptionTeaser(): ?string
    {
        return $this->descriptionTeaser;
    }

    public function setDescriptionTeaser(?string $descriptionTeaser): void
    {
        $this->descriptionTeaser = $descriptionTeaser;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getOgTitle(): ?string
    {
        return $this->ogTitle;
    }

    public function setOgTitle(?string $ogTitle): void
    {
        $this->ogTitle = $ogTitle;
    }

    public function getOgDescription(): ?string
    {
        return $this->ogDescription;
    }

    public function setOgDescription(?string $ogDescription): void
    {
        $this->ogDescription = $ogDescription;
    }

    public function getPackUnit(): ?string
    {
        return $this->packUnit;
    }

    public function setPackUnit(?string $packUnit): void
    {
        $this->packUnit = $packUnit;
    }

    public function getPackUnitPlural(): ?string
    {
        return $this->packUnitPlural;
    }

    public function setPackUnitPlural(?string $packUnitPlural): void
    {
        $this->packUnitPlural = $packUnitPlural;
    }

    public function getTax(): ?TaxEntity
    {
        return $this->tax;
    }

    public function setTax(TaxEntity $tax): void
    {
        $this->tax = $tax;
    }

    public function getManufacturer(): ?BlogManufacturerEntity
    {
        return $this->manufacturer;
    }

    public function setManufacturer(BlogManufacturerEntity $manufacturer): void
    {
        $this->manufacturer = $manufacturer;
    }

    public function getUnit(): ?UnitEntity
    {
        return $this->unit;
    }

    public function setUnit(UnitEntity $unit): void
    {
        $this->unit = $unit;
    }

    public function getPrices(): ?BlogPriceCollection
    {
        return $this->prices;
    }

    public function setPrices(BlogPriceCollection $prices): void
    {
        $this->prices = $prices;
    }

    public function getRestockTime(): ?int
    {
        return $this->restockTime;
    }

    public function setRestockTime(?int $restockTime): void
    {
        $this->restockTime = $restockTime;
    }

    public function getDeliveryDate(): DeliveryDate
    {
        return new DeliveryDate(
            Clock::get()->now()->add(new \DateInterval('P1D')),
            Clock::get()->now()->add(new \DateInterval('P2D'))
        );
    }

    public function getRestockDeliveryDate(): DeliveryDate
    {
        $deliveryDate = $this->getDeliveryDate();

        return $deliveryDate->add(new \DateInterval('P' . $this->getRestockTime() . 'D'));
    }

    public function isReleased(): bool
    {
        if (!$this->getReleaseDate()) {
            return true;
        }

        return $this->releaseDate < Clock::get()->now();
    }

    /**
     * @return array<string>|null
     */
    public function getStreamIds(): ?array
    {
        return $this->streamIds;
    }

    /**
     * @param array<string>|null $streamIds
     */
    public function setStreamIds(?array $streamIds): void
    {
        $this->streamIds = $streamIds;
    }

    /**
     * @return array<string>|null
     */
    public function getOptionIds(): ?array
    {
        return $this->optionIds;
    }

    /**
     * @param array<string>|null $optionIds
     */
    public function setOptionIds(?array $optionIds): void
    {
        $this->optionIds = $optionIds;
    }

    /**
     * @return array<string>|null
     */
    public function getPropertyIds(): ?array
    {
        return $this->propertyIds;
    }

    /**
     * @param array<string>|null $propertyIds
     */
    public function setPropertyIds(?array $propertyIds): void
    {
        $this->propertyIds = $propertyIds;
    }

    public function getCover(): ?BlogMediaEntity
    {
        return $this->cover;
    }

    public function setCover(BlogMediaEntity $cover): void
    {
        $this->cover = $cover;
    }

    public function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPage;
    }

    public function setCmsPage(CmsPageEntity $cmsPage): void
    {
        $this->cmsPage = $cmsPage;
    }

    public function getCmsPageId(): ?string
    {
        return $this->cmsPageId;
    }

    public function setCmsPageId(string $cmsPageId): void
    {
        $this->cmsPageId = $cmsPageId;
    }

    /**
     * @return array<string, array<string, array<string, mixed>>|null>|null
     */
    public function getSlotConfig(): ?array
    {
        return $this->slotConfig;
    }

    /**
     * @param array<string, array<string, array<string, mixed>>|null> $slotConfig
     */
    public function setSlotConfig(array $slotConfig): void
    {
        $this->slotConfig = $slotConfig;
    }

    public function getParent(): ?BlogEntity
    {
        return $this->parent;
    }

    public function setParent(BlogEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?BlogCollection
    {
        return $this->children;
    }

    public function setChildren(BlogCollection $children): void
    {
        $this->children = $children;
    }

    public function getMedia(): ?BlogMediaCollection
    {
        return $this->media;
    }

    public function setMedia(BlogMediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getSearchKeywords(): ?BlogSearchKeywordCollection
    {
        return $this->searchKeywords;
    }

    public function setSearchKeywords(BlogSearchKeywordCollection $searchKeywords): void
    {
        $this->searchKeywords = $searchKeywords;
    }

    public function getTranslations(): ?BlogTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(BlogTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getCustomFieldSets(): ?CustomFieldSetCollection
    {
        return $this->customFieldSets;
    }

    public function setCustomFieldSets(CustomFieldSetCollection $customFieldSets): void
    {
        $this->customFieldSets = $customFieldSets;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    public function getProperties(): ?PropertyGroupOptionCollection
    {
        return $this->properties;
    }

    public function setProperties(PropertyGroupOptionCollection $properties): void
    {
        $this->properties = $properties;
    }

    public function getOptions(): ?PropertyGroupOptionCollection
    {
        return $this->options;
    }

    public function setOptions(PropertyGroupOptionCollection $options): void
    {
        $this->options = $options;
    }

    public function getConfiguratorSettings(): ?BlogConfiguratorSettingCollection
    {
        return $this->configuratorSettings;
    }

    public function setConfiguratorSettings(BlogConfiguratorSettingCollection $configuratorSettings): void
    {
        $this->configuratorSettings = $configuratorSettings;
    }

    public function getCategoriesRo(): ?CategoryCollection
    {
        return $this->categoriesRo;
    }

    public function setCategoriesRo(CategoryCollection $categoriesRo): void
    {
        $this->categoriesRo = $categoriesRo;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getCoverId(): ?string
    {
        return $this->coverId;
    }

    public function setCoverId(string $coverId): void
    {
        $this->coverId = $coverId;
    }

    public function getVisibilities(): ?BlogVisibilityCollection
    {
        return $this->visibilities;
    }

    public function setVisibilities(BlogVisibilityCollection $visibilities): void
    {
        $this->visibilities = $visibilities;
    }

    public function getBlogNumber(): string
    {
        return $this->blogNumber;
    }

    public function setBlogNumber(string $blogNumber): void
    {
        $this->blogNumber = $blogNumber;
    }

    /**
     * @return array<string>|null
     */
    public function getTagIds(): ?array
    {
        return $this->tagIds;
    }

    /**
     * @param array<string> $tagIds
     */
    public function setTagIds(array $tagIds): void
    {
        $this->tagIds = $tagIds;
    }

    /**
     * @return array<string>|null
     */
    public function getVariantRestrictions(): ?array
    {
        return $this->variantRestrictions;
    }

    /**
     * @param array<string>|null $variantRestrictions
     */
    public function setVariantRestrictions(?array $variantRestrictions): void
    {
        $this->variantRestrictions = $variantRestrictions;
    }

    public function getVariantListingConfig(): ?VariantListingConfig
    {
        return $this->variantListingConfig;
    }

    public function setVariantListingConfig(?VariantListingConfig $variantListingConfig): void
    {
        $this->variantListingConfig = $variantListingConfig;
    }

    /**
     * @return array<array<string>>
     */
    public function getVariation(): array
    {
        return $this->variation;
    }

    /**
     * @param array<array<string>> $variation
     */
    public function setVariation(array $variation): void
    {
        $this->variation = $variation;
    }

    public function getAvailableStock(): ?int
    {
        return $this->availableStock;
    }

    public function setAvailableStock(int $availableStock): void
    {
        $this->availableStock = $availableStock;
    }

    public function getAvailable(): bool
    {
        return $this->available;
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function getDeliveryTimeId(): ?string
    {
        return $this->deliveryTimeId;
    }

    public function setDeliveryTimeId(?string $deliveryTimeId): void
    {
        $this->deliveryTimeId = $deliveryTimeId;
    }

    public function getDeliveryTime(): ?DeliveryTimeEntity
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(?DeliveryTimeEntity $deliveryTime): void
    {
        $this->deliveryTime = $deliveryTime;
    }

    public function getChildCount(): ?int
    {
        return $this->childCount;
    }

    public function setChildCount(int $childCount): void
    {
        $this->childCount = $childCount;
    }

    public function getRatingAverage(): ?float
    {
        return $this->ratingAverage;
    }

    public function setRatingAverage(?float $ratingAverage): void
    {
        $this->ratingAverage = $ratingAverage;
    }

    public function getDisplayGroup(): ?string
    {
        return $this->displayGroup;
    }

    public function setDisplayGroup(?string $displayGroup): void
    {
        $this->displayGroup = $displayGroup;
    }

    public function getMainCategories(): ?MainCategoryCollection
    {
        return $this->mainCategories;
    }

    public function setMainCategories(MainCategoryCollection $mainCategories): void
    {
        $this->mainCategories = $mainCategories;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getSeoUrls(): ?SeoUrlCollection
    {
        return $this->seoUrls;
    }

    public function setSeoUrls(SeoUrlCollection $seoUrls): void
    {
        $this->seoUrls = $seoUrls;
    }

    public function getOrderLineItems(): ?OrderLineItemCollection
    {
        return $this->orderLineItems;
    }

    public function setOrderLineItems(OrderLineItemCollection $orderLineItems): void
    {
        $this->orderLineItems = $orderLineItems;
    }

    public function getCrossSellings(): ?BlogCrossSellingCollection
    {
        return $this->crossSellings;
    }

    public function setCrossSellings(BlogCrossSellingCollection $crossSellings): void
    {
        $this->crossSellings = $crossSellings;
    }

    public function getCrossSellingAssignedBlogs(): ?BlogCrossSellingAssignedBlogsCollection
    {
        return $this->crossSellingAssignedBlogs;
    }

    public function setCrossSellingAssignedBlogs(BlogCrossSellingAssignedBlogsCollection $crossSellingAssignedBlogs): void
    {
        $this->crossSellingAssignedBlogs = $crossSellingAssignedBlogs;
    }

    public function getFeatureSetId(): ?string
    {
        return $this->featureSetId;
    }

    public function setFeatureSetId(?string $featureSetId): void
    {
        $this->featureSetId = $featureSetId;
    }

    public function getFeatureSet(): ?BlogFeatureSetEntity
    {
        return $this->featureSet;
    }

    public function setFeatureSet(BlogFeatureSetEntity $featureSet): void
    {
        $this->featureSet = $featureSet;
    }

    public function getCustomFieldSetSelectionActive(): ?bool
    {
        return $this->customFieldSetSelectionActive;
    }

    public function setCustomFieldSetSelectionActive(?bool $customFieldSetSelectionActive): void
    {
        $this->customFieldSetSelectionActive = $customFieldSetSelectionActive;
    }

    /**
     * @return array<string>|null
     */
    public function getCustomSearchKeywords(): ?array
    {
        return $this->customSearchKeywords;
    }

    /**
     * @param array<string>|null $customSearchKeywords
     */
    public function setCustomSearchKeywords(?array $customSearchKeywords): void
    {
        $this->customSearchKeywords = $customSearchKeywords;
    }

    public function getWishlists(): ?UserWishlistBlogCollection
    {
        return $this->wishlists;
    }

    public function setWishlists(UserWishlistBlogCollection $wishlists): void
    {
        $this->wishlists = $wishlists;
    }

    public function getCanonicalBlogId(): ?string
    {
        return $this->canonicalBlogId;
    }

    public function setCanonicalBlogId(string $canonicalBlogId): void
    {
        $this->canonicalBlogId = $canonicalBlogId;
    }

    public function getCanonicalBlog(): ?BlogEntity
    {
        return $this->canonicalBlog;
    }

    public function setCanonicalBlog(BlogEntity $blog): void
    {
        $this->canonicalBlog = $blog;
    }

    public function getStreams(): ?ContentStreamCollection
    {
        return $this->streams;
    }

    public function setStreams(ContentStreamCollection $streams): void
    {
        $this->streams = $streams;
    }

    /**
     * @return array<string>|null
     */
    public function getCategoryIds(): ?array
    {
        return $this->categoryIds;
    }

    /**
     * @param array<string>|null $categoryIds
     */
    public function setCategoryIds(?array $categoryIds): void
    {
        $this->categoryIds = $categoryIds;
    }

    public function getDownloads(): ?BlogDownloadCollection
    {
        return $this->downloads;
    }

    public function setDownloads(BlogDownloadCollection $downloads): void
    {
        $this->downloads = $downloads;
    }

    /**
     * @return array<int, string>
     *
     * @deprecated tag:v6.8.0 - Will be removed. Use getType instead.
     */
    public function getStates(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getStates', 'v6.8.0.0', 'getType')
        );

        return $this->states;
    }

    /**
     * @param array<int, string> $states
     *
     * @deprecated tag:v6.8.0 -- Will be removed. Use setType instead.
     */
    public function setStates(array $states): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'getStates', 'v6.8.0.0', 'getType')
        );

        $this->states = $states;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getOpenGraphMediaId(): ?string
    {
        return $this->openGraphMediaId;
    }

    public function setOpenGraphMediaId(?string $openGraphMediaId): void
    {
        $this->openGraphMediaId = $openGraphMediaId;
    }

    public function getOpenGraphMedia(): ?MediaEntity
    {
        return $this->openGraphMedia;
    }

    public function setOpenGraphMedia(?MediaEntity $openGraphMedia): void
    {
        $this->openGraphMedia = $openGraphMedia;
    }
}
