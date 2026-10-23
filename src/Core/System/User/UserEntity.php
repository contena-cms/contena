<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderUser\OrderUserCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Deprecation\BCChange\ParameterTypeWidening;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Salutation\SalutationEntity;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\AdminUser\AdminUserEntity;

class UserEntity extends Entity implements \Stringable
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    final public const ACCOUNT_TYPE_PRIVATE = 'private';
    final public const ACCOUNT_TYPE_BUSINESS = 'business';

    protected string $groupId;

    protected string $channelId;

    protected string $languageId;

    protected ?string $lastPaymentMethodId = null;

    protected string $defaultBillingAddressId;

    protected string $defaultShippingAddressId;

    protected string $userNumber;

    protected ?string $salutationId = null;

    protected string $firstName;

    protected string $lastName;

    protected ?string $company = null;

    /**
     * @internal
     */
    protected ?string $password = null;

    protected string $email;

    protected ?string $title = null;

    /**
     * @var array<string>|null
     */
    protected ?array $vatIds = null;

    protected ?string $affiliateCode = null;

    protected ?string $campaignCode = null;

    protected bool $active;

    protected bool $doubleOptInRegistration;

    protected ?\DateTimeInterface $doubleOptInEmailSentDate = null;

    protected ?\DateTimeInterface $doubleOptInConfirmDate = null;

    protected ?string $hash = null;

    protected bool $guest;

    protected ?\DateTimeInterface $firstLogin = null;

    protected ?\DateTimeInterface $lastLogin = null;

    protected string $accountType;

    /**
     * @var array<string>|null
     *
     * @internal
     */
    protected ?array $newsletterChannelIds = null;

    protected ?\DateTimeInterface $birthday = null;

    protected ?\DateTimeInterface $lastOrderDate = null;

    protected int $orderCount;

    protected float $orderTotalAmount;

    protected int $reviewCount;

    /**
     * @internal
     */
    protected ?string $legacyEncoder = null;

    /**
     * @internal
     */
    protected ?string $legacyPassword = null;

    protected ?UserGroupEntity $group = null;

    protected ?ChannelEntity $channel = null;

    protected ?LanguageEntity $language = null;

    protected ?PaymentMethodEntity $lastPaymentMethod = null;

    protected ?SalutationEntity $salutation = null;

    protected ?UserAddressEntity $defaultBillingAddress = null;

    protected ?UserAddressEntity $defaultShippingAddress = null;

    protected ?UserAddressEntity $activeBillingAddress = null;

    protected ?UserAddressEntity $activeShippingAddress = null;

    protected ?UserAddressCollection $addresses = null;

    protected ?OrderUserCollection $orderUsers = null;

    protected int $autoIncrement;

    protected ?TagCollection $tags = null;

    /**
     * @var list<string>|null
     */
    protected ?array $tagIds = null;

    protected ?PromotionCollection $promotions = null;

    protected ?UserRecoveryEntity $recoveryUser = null;

    protected ?BlogReviewCollection $blogReviews = null;

    protected ?string $remoteAddress = null;

    protected ?string $requestedGroupId = null;

    protected ?UserGroupEntity $requestedGroup = null;

    protected ?string $boundChannelId = null;

    protected ?ChannelEntity $boundChannel = null;

    protected ?UserWishlistCollection $wishlists = null;

    protected ?string $createdById = null;

    protected ?AdminUserEntity $createdBy = null;

    protected ?string $updatedById = null;

    protected ?AdminUserEntity $updatedBy = null;

    public function __toString(): string
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLastPaymentMethodId(): ?string
    {
        return $this->lastPaymentMethodId;
    }

    public function setLastPaymentMethodId(?string $lastPaymentMethodId): void
    {
        $this->lastPaymentMethodId = $lastPaymentMethodId;
    }

    public function getDefaultBillingAddressId(): string
    {
        return $this->defaultBillingAddressId;
    }

    public function setDefaultBillingAddressId(string $defaultBillingAddressId): void
    {
        $this->defaultBillingAddressId = $defaultBillingAddressId;
    }

    public function getDefaultShippingAddressId(): string
    {
        return $this->defaultShippingAddressId;
    }

    public function setDefaultShippingAddressId(string $defaultShippingAddressId): void
    {
        $this->defaultShippingAddressId = $defaultShippingAddressId;
    }

    public function getUserNumber(): string
    {
        return $this->userNumber;
    }

    public function setUserNumber(string $userNumber): void
    {
        $this->userNumber = $userNumber;
    }

    public function getSalutationId(): ?string
    {
        return $this->salutationId;
    }

    public function setSalutationId(string $salutationId): void
    {
        $this->salutationId = $salutationId;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(string $company): void
    {
        $this->company = $company;
    }

    /**
     * @internal
     */
    public function getPassword(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('password');

        return $this->password;
    }

    /**
     * @internal
     */
    public function setPassword(#[\SensitiveParameter] ?string $password): void
    {
        $this->password = $password;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return array<string>|null
     */
    public function getVatIds(): ?array
    {
        return $this->vatIds;
    }

    /**
     * @param array<string>|null $vatIds
     */
    public function setVatIds(?array $vatIds): void
    {
        $this->vatIds = $vatIds;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getDoubleOptInRegistration(): bool
    {
        return $this->doubleOptInRegistration;
    }

    public function setDoubleOptInRegistration(bool $doubleOptInRegistration): void
    {
        $this->doubleOptInRegistration = $doubleOptInRegistration;
    }

    public function getDoubleOptInEmailSentDate(): ?\DateTimeInterface
    {
        return $this->doubleOptInEmailSentDate;
    }

    public function setDoubleOptInEmailSentDate(\DateTimeInterface $doubleOptInEmailSentDate): void
    {
        $this->doubleOptInEmailSentDate = $doubleOptInEmailSentDate;
    }

    public function getDoubleOptInConfirmDate(): ?\DateTimeInterface
    {
        return $this->doubleOptInConfirmDate;
    }

    public function setDoubleOptInConfirmDate(\DateTimeInterface $doubleOptInConfirmDate): void
    {
        $this->doubleOptInConfirmDate = $doubleOptInConfirmDate;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getGuest(): bool
    {
        return $this->guest;
    }

    public function setGuest(bool $guest): void
    {
        $this->guest = $guest;
    }

    public function getFirstLogin(): ?\DateTimeInterface
    {
        return $this->firstLogin;
    }

    public function setFirstLogin(?\DateTimeInterface $firstLogin): void
    {
        $this->firstLogin = $firstLogin;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): void
    {
        $this->lastLogin = $lastLogin;
    }

    /**
     * @internal
     *
     * @return array<string>|null
     */
    public function getNewsletterChannelIds(): ?array
    {
        $this->checkIfPropertyAccessIsAllowed('newsletterChannelIds');

        return $this->newsletterChannelIds;
    }

    /**
     * @internal
     *
     * @param array<string>|null $newsletterChannelIds
     */
    public function setNewsletterChannelIds(?array $newsletterChannelIds): void
    {
        $this->newsletterChannelIds = $newsletterChannelIds;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getLastOrderDate(): ?\DateTimeInterface
    {
        return $this->lastOrderDate;
    }

    public function setLastOrderDate(?\DateTimeInterface $lastOrderDate): void
    {
        $this->lastOrderDate = $lastOrderDate;
    }

    public function getOrderCount(): int
    {
        return $this->orderCount;
    }

    public function setOrderCount(int $orderCount): void
    {
        $this->orderCount = $orderCount;
    }

    public function getOrderTotalAmount(): float
    {
        return $this->orderTotalAmount;
    }

    public function getReviewCount(): int
    {
        return $this->reviewCount;
    }

    public function setReviewCount(int $reviewCount): void
    {
        $this->reviewCount = $reviewCount;
    }

    public function setOrderTotalAmount(float $orderTotalAmount): void
    {
        $this->orderTotalAmount = $orderTotalAmount;
    }

    /**
     * @internal
     */
    public function getLegacyEncoder(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('legacyEncoder');

        return $this->legacyEncoder;
    }

    /**
     * @internal
     */
    public function setLegacyEncoder(?string $legacyEncoder): void
    {
        $this->legacyEncoder = $legacyEncoder;
    }

    /**
     * @internal
     */
    public function getLegacyPassword(): ?string
    {
        $this->checkIfPropertyAccessIsAllowed('legacyPassword');

        return $this->legacyPassword;
    }

    /**
     * @internal
     */
    public function setLegacyPassword(#[\SensitiveParameter] ?string $legacyPassword): void
    {
        $this->legacyPassword = $legacyPassword;
    }

    public function hasLegacyPassword(): bool
    {
        return $this->legacyPassword !== null && $this->legacyEncoder !== null;
    }

    public function getGroup(): ?UserGroupEntity
    {
        return $this->group;
    }

    public function setGroup(UserGroupEntity $group): void
    {
        $this->group = $group;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }

    public function getLastPaymentMethod(): ?PaymentMethodEntity
    {
        return $this->lastPaymentMethod;
    }

    public function setLastPaymentMethod(PaymentMethodEntity $lastPaymentMethod): void
    {
        $this->lastPaymentMethod = $lastPaymentMethod;
    }

    public function getSalutation(): ?SalutationEntity
    {
        return $this->salutation;
    }

    public function setSalutation(SalutationEntity $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getDefaultBillingAddress(): ?UserAddressEntity
    {
        return $this->defaultBillingAddress;
    }

    public function setDefaultBillingAddress(UserAddressEntity $defaultBillingAddress): void
    {
        $this->defaultBillingAddress = $defaultBillingAddress;
    }

    public function getDefaultShippingAddress(): ?UserAddressEntity
    {
        return $this->defaultShippingAddress;
    }

    public function setDefaultShippingAddress(UserAddressEntity $defaultShippingAddress): void
    {
        $this->defaultShippingAddress = $defaultShippingAddress;
    }

    public function getActiveBillingAddress(): ?UserAddressEntity
    {
        return $this->activeBillingAddress ?? $this->defaultBillingAddress;
    }

    #[ParameterTypeWidening(version: 'v6.8.0', parameterName: 'activeBillingAddress', newType: '?' . UserAddressEntity::class)]
    public function setActiveBillingAddress(/* ? */ UserAddressEntity $activeBillingAddress): void
    {
        $this->activeBillingAddress = $activeBillingAddress;
    }

    public function getActiveShippingAddress(): ?UserAddressEntity
    {
        return $this->activeShippingAddress ?? $this->defaultShippingAddress;
    }

    #[ParameterTypeWidening(version: 'v6.8.0', parameterName: 'activeShippingAddress', newType: '?' . UserAddressEntity::class)]
    public function setActiveShippingAddress(/* ? */ UserAddressEntity $activeShippingAddress): void
    {
        $this->activeShippingAddress = $activeShippingAddress;
    }

    public function getAddresses(): ?UserAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(UserAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getOrderUsers(): ?OrderUserCollection
    {
        return $this->orderUsers;
    }

    public function setOrderUsers(OrderUserCollection $orderUsers): void
    {
        $this->orderUsers = $orderUsers;
    }

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
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
     * @return list<string>|null
     */
    public function getTagIds(): ?array
    {
        return $this->tagIds;
    }

    /**
     * @param list<string> $tagIds
     */
    public function setTagIds(array $tagIds): void
    {
        $this->tagIds = $tagIds;
    }

    /**
     * Gets a list of all promotions where the user
     * is assigned to within the "persona" conditions.
     */
    public function getPromotions(): ?PromotionCollection
    {
        return $this->promotions;
    }

    /**
     * Sets a list of all promotions where the user
     * should be assigned to within the "persona" conditions.
     */
    public function setPromotions(PromotionCollection $promotions): void
    {
        $this->promotions = $promotions;
    }

    public function getBlogReviews(): ?BlogReviewCollection
    {
        return $this->blogReviews;
    }

    public function setBlogReviews(BlogReviewCollection $blogReviews): void
    {
        $this->blogReviews = $blogReviews;
    }

    public function getRecoveryUser(): ?UserRecoveryEntity
    {
        return $this->recoveryUser;
    }

    public function setRecoveryUser(?UserRecoveryEntity $recoveryUser): void
    {
        $this->recoveryUser = $recoveryUser;
    }

    public function getAffiliateCode(): ?string
    {
        return $this->affiliateCode;
    }

    public function setAffiliateCode(?string $affiliateCode): void
    {
        $this->affiliateCode = $affiliateCode;
    }

    public function getCampaignCode(): ?string
    {
        return $this->campaignCode;
    }

    public function setCampaignCode(?string $campaignCode): void
    {
        $this->campaignCode = $campaignCode;
    }

    public function getRemoteAddress(): ?string
    {
        return $this->remoteAddress;
    }

    public function setRemoteAddress(?string $remoteAddress): void
    {
        $this->remoteAddress = $remoteAddress;
    }

    public function getRequestedGroupId(): ?string
    {
        return $this->requestedGroupId;
    }

    public function setRequestedGroupId(?string $requestedGroupId): void
    {
        $this->requestedGroupId = $requestedGroupId;
    }

    public function getRequestedGroup(): ?UserGroupEntity
    {
        return $this->requestedGroup;
    }

    public function setRequestedGroup(?UserGroupEntity $requestedGroup): void
    {
        $this->requestedGroup = $requestedGroup;
    }

    public function getBoundChannelId(): ?string
    {
        return $this->boundChannelId;
    }

    public function setBoundChannelId(?string $boundChannelId): void
    {
        $this->boundChannelId = $boundChannelId;
    }

    public function getBoundChannel(): ?ChannelEntity
    {
        return $this->boundChannel;
    }

    public function setBoundChannel(ChannelEntity $boundChannel): void
    {
        $this->boundChannel = $boundChannel;
    }

    public function setAccountType(string $accountType): void
    {
        $this->accountType = $accountType;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function getWishlists(): ?UserWishlistCollection
    {
        return $this->wishlists;
    }

    public function setWishlists(UserWishlistCollection $wishlists): void
    {
        $this->wishlists = $wishlists;
    }

    public function getCreatedById(): ?string
    {
        return $this->createdById;
    }

    public function setCreatedById(string $createdById): void
    {
        $this->createdById = $createdById;
    }

    public function getCreatedBy(): ?AdminUserEntity
    {
        return $this->createdBy;
    }

    public function setCreatedBy(AdminUserEntity $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getUpdatedById(): ?string
    {
        return $this->updatedById;
    }

    public function setUpdatedById(string $updatedById): void
    {
        $this->updatedById = $updatedById;
    }

    public function getUpdatedBy(): ?AdminUserEntity
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(AdminUserEntity $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
    }
}
