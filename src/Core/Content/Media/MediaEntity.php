<?php declare(strict_types=1);

namespace Contena\Core\Content\Media;

use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileEntity;
use Contena\Core\Checkout\DocumentV2\DocumentCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Contena\Core\Content\Cms\CmsPageCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaCollection;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationCollection;
use Contena\Core\Content\Media\MediaType\MediaType;
use Contena\Core\Content\Media\MediaType\SpatialMediaTypeInterface;
use Contena\Core\Content\Media\MediaType\SpatialObjectType;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogDownload\BlogDownloadCollection;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodCollection;
use Contena\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @phpstan-type MediaConfig array{'spatialObject': array{'arReady': bool, 'arPlacement': string}}
 */
class MediaEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $admin_userId = null;

    protected ?string $mimeType = null;

    protected ?string $fileExtension = null;

    protected ?int $fileSize = null;

    protected ?string $title = null;

    protected ?string $metaDataRaw = null;

    /**
     * @internal
     */
    protected ?string $mediaTypeRaw = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $metaData = null;

    protected ?MediaType $mediaType = null;

    protected ?\DateTimeInterface $uploadedAt = null;

    protected ?string $alt = null;

    protected string $url = '';

    protected ?string $fileName = null;

    protected ?AdminUserEntity $admin_user = null;

    protected ?MediaTranslationCollection $translations = null;

    protected ?CategoryCollection $categories = null;

    protected ?BlogManufacturerCollection $blogManufacturers = null;

    protected ?BlogMediaCollection $blogMedia = null;

    protected ?BlogCollection $blogOpenGraphImages = null;

    protected ?BlogCollection $blogGuaranteeTerms = null;

    protected ?AdminUserCollection $avatarUsers = null;

    protected ?MediaThumbnailCollection $thumbnails = null;

    protected ?string $mediaFolderId = null;

    protected ?MediaFolderEntity $mediaFolder = null;

    protected bool $hasFile = false;

    protected bool $private = false;

    protected ?PropertyGroupOptionCollection $propertyGroupOptions = null;

    protected ?MailTemplateMediaCollection $mailTemplateMedia = null;

    protected ?TagCollection $tags = null;

    /**
     * @internal
     */
    protected ?string $thumbnailsRo = null;

    protected ?string $path = null;

    protected ?DocumentBaseConfigCollection $documentBaseConfigs = null;

    protected ?ShippingMethodCollection $shippingMethods = null;

    protected ?PaymentMethodCollection $paymentMethods = null;

    protected ?BlogConfiguratorSettingCollection $blogConfiguratorSettings = null;

    protected ?OrderLineItemCollection $orderLineItems = null;

    protected ?CmsBlockCollection $cmsBlocks = null;

    protected ?CmsSectionCollection $cmsSections = null;

    protected ?CmsPageCollection $cmsPages = null;

    protected ?DocumentCollection $documents = null;

    protected ?DocumentCollection $a11yDocuments = null;

    /**
     * @internal
     */
    protected ?DocumentFileEntity $documentFile = null;

    protected ?AppPaymentMethodCollection $appPaymentMethods = null;

    /**
     * @var EntityCollection<AppShippingMethodEntity>|null
     */
    protected ?EntityCollection $appShippingMethods = null;

    protected ?BlogDownloadCollection $blogDownloads = null;

    protected ?OrderLineItemDownloadCollection $orderLineItemDownloads = null;

    /**
     * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
     *
     * @var MediaConfig|null
     */
    protected ?array $config = null;

    /**
     * @internal
     */
    protected ?string $fileHash = null;

    public function get(string $property)
    {
        if ($property === 'hasFile') {
            return $this->hasFile();
        }

        return parent::get($property);
    }

    public function getUserId(): ?string
    {
        return $this->admin_userId;
    }

    public function setUserId(string $admin_userId): void
    {
        $this->admin_userId = $admin_userId;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getFileExtension(): ?string
    {
        return $this->fileExtension;
    }

    public function setFileExtension(string $fileExtension): void
    {
        $this->fileExtension = $fileExtension;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetaData(): ?array
    {
        return $this->metaData;
    }

    /**
     * @param array<string, mixed> $metaData
     */
    public function setMetaData(array $metaData): void
    {
        $this->metaData = $metaData;
    }

    public function getMediaType(): ?MediaType
    {
        return $this->mediaType;
    }

    public function setMediaType(MediaType $mediaType): void
    {
        $this->mediaType = $mediaType;
    }

    public function getUploadedAt(): ?\DateTimeInterface
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeInterface $uploadedAt): void
    {
        $this->uploadedAt = $uploadedAt;
    }

    /**
     * @internal
     */
    public function getDocumentFile(): ?DocumentFileEntity
    {
        return $this->documentFile;
    }

    /**
     * @internal
     */
    public function setDocumentFile(?DocumentFileEntity $documentFile): void
    {
        $this->documentFile = $documentFile;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(string $alt): void
    {
        $this->alt = $alt;
    }

    public function getUser(): ?AdminUserEntity
    {
        return $this->admin_user;
    }

    public function setUser(AdminUserEntity $admin_user): void
    {
        $this->admin_user = $admin_user;
    }

    public function getTranslations(): ?MediaTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(MediaTranslationCollection $translations): void
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

    public function getBlogManufacturers(): ?BlogManufacturerCollection
    {
        return $this->blogManufacturers;
    }

    public function setBlogManufacturers(BlogManufacturerCollection $blogManufacturers): void
    {
        $this->blogManufacturers = $blogManufacturers;
    }

    public function getBlogMedia(): ?BlogMediaCollection
    {
        return $this->blogMedia;
    }

    public function setBlogMedia(BlogMediaCollection $blogMedia): void
    {
        $this->blogMedia = $blogMedia;
    }

    public function getBlogOpenGraphImages(): ?BlogCollection
    {
        return $this->blogOpenGraphImages;
    }

    public function setBlogOpenGraphImages(BlogCollection $blogOpenGraphImages): void
    {
        $this->blogOpenGraphImages = $blogOpenGraphImages;
    }

    public function getBlogGuaranteeTerms(): ?BlogCollection
    {
        return $this->blogGuaranteeTerms;
    }

    public function setBlogGuaranteeTerms(BlogCollection $blogGuaranteeTerms): void
    {
        $this->blogGuaranteeTerms = $blogGuaranteeTerms;
    }

    public function getAvatarUsers(): ?AdminUserCollection
    {
        return $this->avatarUsers;
    }

    public function setAvatarUsers(AdminUserCollection $avatarUsers): void
    {
        $this->avatarUsers = $avatarUsers;
    }

    public function getThumbnails(): ?MediaThumbnailCollection
    {
        return $this->thumbnails;
    }

    public function setThumbnails(MediaThumbnailCollection $thumbnailCollection): void
    {
        $this->thumbnails = $thumbnailCollection;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function hasFile(): bool
    {
        $hasFile = $this->mimeType !== null && $this->fileExtension !== null && $this->fileName !== null;

        return $this->hasFile = $hasFile || $this->path !== null;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getFileNameIncludingExtension(): ?string
    {
        if ($this->fileName === null || $this->fileExtension === null) {
            return null;
        }

        return \sprintf('%s.%s', $this->fileName, $this->fileExtension);
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function getMediaFolderId(): ?string
    {
        return $this->mediaFolderId;
    }

    public function setMediaFolderId(string $mediaFolderId): void
    {
        $this->mediaFolderId = $mediaFolderId;
    }

    public function getMediaFolder(): ?MediaFolderEntity
    {
        return $this->mediaFolder;
    }

    public function setMediaFolder(MediaFolderEntity $mediaFolder): void
    {
        $this->mediaFolder = $mediaFolder;
    }

    public function getPropertyGroupOptions(): ?PropertyGroupOptionCollection
    {
        return $this->propertyGroupOptions;
    }

    public function setPropertyGroupOptions(PropertyGroupOptionCollection $propertyGroupOptions): void
    {
        $this->propertyGroupOptions = $propertyGroupOptions;
    }

    public function getMetaDataRaw(): ?string
    {
        return $this->metaDataRaw;
    }

    public function setMetaDataRaw(string $metaDataRaw): void
    {
        $this->metaDataRaw = $metaDataRaw;
    }

    /**
     * @internal
     */
    public function getMediaTypeRaw(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('mediaTypeRaw');

        return $this->mediaTypeRaw;
    }

    /**
     * @internal
     */
    public function setMediaTypeRaw(string $mediaTypeRaw): void
    {
        $this->mediaTypeRaw = $mediaTypeRaw;
    }

    public function getMailTemplateMedia(): ?MailTemplateMediaCollection
    {
        return $this->mailTemplateMedia;
    }

    public function setMailTemplateMedia(MailTemplateMediaCollection $mailTemplateMedia): void
    {
        $this->mailTemplateMedia = $mailTemplateMedia;
    }

    public function getTags(): ?TagCollection
    {
        return $this->tags;
    }

    public function setTags(TagCollection $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * @internal
     */
    public function getThumbnailsRo(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('thumbnailsRo');

        return $this->thumbnailsRo;
    }

    /**
     * @internal
     */
    public function setThumbnailsRo(string $thumbnailsRo): void
    {
        $this->thumbnailsRo = $thumbnailsRo;
    }

    public function getDocumentBaseConfigs(): ?DocumentBaseConfigCollection
    {
        return $this->documentBaseConfigs;
    }

    public function setDocumentBaseConfigs(DocumentBaseConfigCollection $documentBaseConfigs): void
    {
        $this->documentBaseConfigs = $documentBaseConfigs;
    }

    public function getShippingMethods(): ?ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    public function getPaymentMethods(): ?PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();
        unset($data['metaDataRaw'], $data['mediaTypeRaw']);
        $data['hasFile'] = $this->hasFile();

        return $data;
    }

    public function getBlogConfiguratorSettings(): ?BlogConfiguratorSettingCollection
    {
        return $this->blogConfiguratorSettings;
    }

    public function setBlogConfiguratorSettings(BlogConfiguratorSettingCollection $blogConfiguratorSettings): void
    {
        $this->blogConfiguratorSettings = $blogConfiguratorSettings;
    }

    public function getOrderLineItems(): ?OrderLineItemCollection
    {
        return $this->orderLineItems;
    }

    public function setOrderLineItems(OrderLineItemCollection $orderLineItems): void
    {
        $this->orderLineItems = $orderLineItems;
    }

    public function getCmsBlocks(): ?CmsBlockCollection
    {
        return $this->cmsBlocks;
    }

    public function setCmsBlocks(CmsBlockCollection $cmsBlocks): void
    {
        $this->cmsBlocks = $cmsBlocks;
    }

    public function getCmsSections(): ?CmsSectionCollection
    {
        return $this->cmsSections;
    }

    public function setCmsSections(CmsSectionCollection $cmsSections): void
    {
        $this->cmsSections = $cmsSections;
    }

    public function getCmsPages(): ?CmsPageCollection
    {
        return $this->cmsPages;
    }

    public function setCmsPages(CmsPageCollection $cmsPages): void
    {
        $this->cmsPages = $cmsPages;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): void
    {
        $this->private = $private;
    }

    public function getDocuments(): ?DocumentCollection
    {
        return $this->documents;
    }

    public function setDocuments(DocumentCollection $documents): void
    {
        $this->documents = $documents;
    }

    public function getA11yDocuments(): ?DocumentCollection
    {
        return $this->a11yDocuments;
    }

    public function setA11yDocuments(DocumentCollection $a11yDocuments): void
    {
        $this->a11yDocuments = $a11yDocuments;
    }

    public function getAppPaymentMethods(): ?AppPaymentMethodCollection
    {
        return $this->appPaymentMethods;
    }

    public function setAppPaymentMethods(AppPaymentMethodCollection $appPaymentMethods): void
    {
        $this->appPaymentMethods = $appPaymentMethods;
    }

    /**
     * @return EntityCollection<AppShippingMethodEntity>|null
     */
    public function getAppShippingMethods(): ?EntityCollection
    {
        return $this->appShippingMethods;
    }

    /**
     * @param EntityCollection<AppShippingMethodEntity> $appShippingMethods
     */
    public function setAppShippingMethods(EntityCollection $appShippingMethods): void
    {
        $this->appShippingMethods = $appShippingMethods;
    }

    public function getBlogDownloads(): ?BlogDownloadCollection
    {
        return $this->blogDownloads;
    }

    public function setBlogDownloads(BlogDownloadCollection $blogDownloads): void
    {
        $this->blogDownloads = $blogDownloads;
    }

    public function getOrderLineItemDownloads(): ?OrderLineItemDownloadCollection
    {
        return $this->orderLineItemDownloads;
    }

    public function setOrderLineItemDownloads(OrderLineItemDownloadCollection $orderLineItemDownloads): void
    {
        $this->orderLineItemDownloads = $orderLineItemDownloads;
    }

    public function hasPath(): bool
    {
        return $this->path !== null;
    }

    public function getPath(): string
    {
        return $this->path ?? '';
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
     *
     * @return MediaConfig|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
     *
     * @param MediaConfig|null $configuration
     */
    public function setConfig(?array $configuration): void
    {
        $this->config = $configuration;
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
     */
    public function isSpatialObject(): bool
    {
        return $this->mediaType instanceof SpatialObjectType;
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:SPATIAL_BASES
     */
    public function isSpatial(): bool
    {
        return $this->mediaType instanceof SpatialMediaTypeInterface;
    }

    /**
     * @internal
     */
    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }
}
