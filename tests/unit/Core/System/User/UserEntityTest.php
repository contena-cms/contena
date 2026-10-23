<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderUser\OrderUserCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Salutation\SalutationEntity;
use Contena\Core\System\Tag\TagCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[CoversClass(UserEntity::class)]
class UserEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $group = new UserGroupEntity();
        $requestedGroup = new UserGroupEntity();
        $channel = new ChannelEntity();
        $boundChannel = new ChannelEntity();
        $language = new LanguageEntity();
        $paymentMethod = new PaymentMethodEntity();
        $salutation = new SalutationEntity();
        $billingAddress = new UserAddressEntity();
        $shippingAddress = new UserAddressEntity();
        $activeBillingAddress = new UserAddressEntity();
        $activeShippingAddress = new UserAddressEntity();
        $addresses = new UserAddressCollection();
        $orderUsers = new OrderUserCollection();
        $tags = new TagCollection();
        $promotions = new PromotionCollection();
        $reviews = new BlogReviewCollection();
        $recovery = new UserRecoveryEntity();
        $wishlists = new UserWishlistCollection();
        $createdBy = new AdminUserEntity();
        $updatedBy = new AdminUserEntity();
        $emailSentDate = new \DateTimeImmutable('2024-01-01 10:00:00');
        $confirmDate = new \DateTimeImmutable('2024-01-02 10:00:00');
        $firstLogin = new \DateTimeImmutable('2024-01-03 10:00:00');
        $lastLogin = new \DateTimeImmutable('2024-01-04 10:00:00');
        $birthday = new \DateTimeImmutable('1990-05-06');
        $lastOrderDate = new \DateTimeImmutable('2024-01-05 10:00:00');

        $user = new UserEntity();
        $user->setGroupId('group-id');
        $user->setChannelId('sales-channel-id');
        $user->setLanguageId('language-id');
        $user->setLastPaymentMethodId('payment-method-id');
        $user->setDefaultBillingAddressId('billing-address-id');
        $user->setDefaultShippingAddressId('shipping-address-id');
        $user->setUserNumber('10001');
        $user->setSalutationId('salutation-id');
        $user->setName('Ada');
        $user->setLastName('Lovelace');
        $user->setCompany('Analytical Engines');
        $user->setEmail('ada@example.com');
        $user->setTitle('Dr.');
        $user->setVatIds(['DE123456789']);
        $user->setActive(true);
        $user->setDoubleOptInRegistration(true);
        $user->setDoubleOptInEmailSentDate($emailSentDate);
        $user->setDoubleOptInConfirmDate($confirmDate);
        $user->setHash('hash');
        $user->setGuest(false);
        $user->setFirstLogin($firstLogin);
        $user->setLastLogin($lastLogin);
        $user->setBirthday($birthday);
        $user->setLastOrderDate($lastOrderDate);
        $user->setOrderCount(3);
        $user->setOrderTotalAmount(99.5);
        $user->setReviewCount(2);
        $user->setGroup($group);
        $user->setChannel($channel);
        $user->setLanguage($language);
        $user->setLastPaymentMethod($paymentMethod);
        $user->setSalutation($salutation);
        $user->setDefaultBillingAddress($billingAddress);
        $user->setDefaultShippingAddress($shippingAddress);
        $user->setActiveBillingAddress($activeBillingAddress);
        $user->setActiveShippingAddress($activeShippingAddress);
        $user->setAddresses($addresses);
        $user->setOrderUsers($orderUsers);
        $user->setAutoIncrement(7);
        $user->setTags($tags);
        $user->setTagIds(['tag-id']);
        $user->setPromotions($promotions);
        $user->setBlogReviews($reviews);
        $user->setRecoveryUser($recovery);
        $user->setAffiliateCode('affiliate');
        $user->setCampaignCode('campaign');
        $user->setRemoteAddress('127.0.0.1');
        $user->setRequestedGroupId('requested-group-id');
        $user->setRequestedGroup($requestedGroup);
        $user->setBoundChannelId('bound-sales-channel-id');
        $user->setBoundChannel($boundChannel);
        $user->setAccountType('business');
        $user->setWishlists($wishlists);
        $user->setCreatedById('created-by-id');
        $user->setCreatedBy($createdBy);
        $user->setUpdatedById('updated-by-id');
        $user->setUpdatedBy($updatedBy);

        static::assertSame('group-id', $user->getGroupId());
        static::assertSame('sales-channel-id', $user->getChannelId());
        static::assertSame('language-id', $user->getLanguageId());
        static::assertSame('payment-method-id', $user->getLastPaymentMethodId());
        static::assertSame('billing-address-id', $user->getDefaultBillingAddressId());
        static::assertSame('shipping-address-id', $user->getDefaultShippingAddressId());
        static::assertSame('10001', $user->getUserNumber());
        static::assertSame('salutation-id', $user->getSalutationId());
        static::assertSame('Ada', $user->getName());
        static::assertSame('Lovelace', $user->getLastName());
        static::assertSame('Ada Lovelace', (string) $user);
        static::assertSame('Analytical Engines', $user->getCompany());
        static::assertSame('ada@example.com', $user->getEmail());
        static::assertSame('Dr.', $user->getTitle());
        static::assertSame(['DE123456789'], $user->getVatIds());
        static::assertTrue($user->getActive());
        static::assertTrue($user->getDoubleOptInRegistration());
        static::assertSame($emailSentDate, $user->getDoubleOptInEmailSentDate());
        static::assertSame($confirmDate, $user->getDoubleOptInConfirmDate());
        static::assertSame('hash', $user->getHash());
        static::assertFalse($user->getGuest());
        static::assertSame($firstLogin, $user->getFirstLogin());
        static::assertSame($lastLogin, $user->getLastLogin());
        static::assertSame($birthday, $user->getBirthday());
        static::assertSame($lastOrderDate, $user->getLastOrderDate());
        static::assertSame(3, $user->getOrderCount());
        static::assertSame(99.5, $user->getOrderTotalAmount());
        static::assertSame(2, $user->getReviewCount());
        static::assertSame($group, $user->getGroup());
        static::assertSame($channel, $user->getChannel());
        static::assertSame($language, $user->getLanguage());
        static::assertSame($paymentMethod, $user->getLastPaymentMethod());
        static::assertSame($salutation, $user->getSalutation());
        static::assertSame($billingAddress, $user->getDefaultBillingAddress());
        static::assertSame($shippingAddress, $user->getDefaultShippingAddress());
        static::assertSame($activeBillingAddress, $user->getActiveBillingAddress());
        static::assertSame($activeShippingAddress, $user->getActiveShippingAddress());
        static::assertSame($addresses, $user->getAddresses());
        static::assertSame($orderUsers, $user->getOrderUsers());
        static::assertSame(7, $user->getAutoIncrement());
        static::assertSame($tags, $user->getTags());
        static::assertSame(['tag-id'], $user->getTagIds());
        static::assertSame($promotions, $user->getPromotions());
        static::assertSame($reviews, $user->getBlogReviews());
        static::assertSame($recovery, $user->getRecoveryUser());
        static::assertSame('affiliate', $user->getAffiliateCode());
        static::assertSame('campaign', $user->getCampaignCode());
        static::assertSame('127.0.0.1', $user->getRemoteAddress());
        static::assertSame('requested-group-id', $user->getRequestedGroupId());
        static::assertSame($requestedGroup, $user->getRequestedGroup());
        static::assertSame('bound-sales-channel-id', $user->getBoundChannelId());
        static::assertSame($boundChannel, $user->getBoundChannel());
        static::assertSame('business', $user->getAccountType());
        static::assertSame($wishlists, $user->getWishlists());
        static::assertSame('created-by-id', $user->getCreatedById());
        static::assertSame($createdBy, $user->getCreatedBy());
        static::assertSame('updated-by-id', $user->getUpdatedById());
        static::assertSame($updatedBy, $user->getUpdatedBy());
    }

    public function testHasLegacyPasswordNeedsBothHashAndEncoder(): void
    {
        $user = new UserEntity();
        static::assertFalse($user->hasLegacyPassword());

        $user->setLegacyPassword('hash');
        static::assertFalse($user->hasLegacyPassword());

        $user->setLegacyEncoder('md5');
        static::assertTrue($user->hasLegacyPassword());
    }

    /**
     * @param \Closure(UserEntity): void $write
     * @param \Closure(UserEntity): mixed $read
     */
    #[TestDox('$_dataName is readable outside of a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsReadableOutsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $user = $this->userWithInternalProperties();
        $write($user);

        static::assertSame($expected, $read($user));
    }

    /**
     * @param \Closure(UserEntity): void $write
     * @param \Closure(UserEntity): mixed $read
     */
    #[TestDox('$_dataName is guarded inside a Twig rendering context')]
    #[DataProvider('internalPropertyProvider')]
    public function testInternalPropertyIsGuardedInsideTwig(\Closure $write, \Closure $read, mixed $expected, string $property): void
    {
        $user = $this->userWithInternalProperties();
        $write($user);

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed($property, UserEntity::class));
        $read($user);
    }

    /**
     * @return \Generator<string, array{0: \Closure(UserEntity): void, 1: \Closure(UserEntity): mixed, 2: mixed, 3: string}>
     */
    public static function internalPropertyProvider(): \Generator
    {
        yield 'password' => [
            static fn (UserEntity $user) => $user->setPassword('secret'),
            static fn (UserEntity $user) => $user->getPassword(),
            'secret',
            'password',
        ];

        yield 'newsletterChannelIds' => [
            static fn (UserEntity $user) => $user->setNewsletterChannelIds(['a' => 'b']),
            static fn (UserEntity $user) => $user->getNewsletterChannelIds(),
            ['a' => 'b'],
            'newsletterChannelIds',
        ];

        yield 'legacyEncoder' => [
            static fn (UserEntity $user) => $user->setLegacyEncoder('md5'),
            static fn (UserEntity $user) => $user->getLegacyEncoder(),
            'md5',
            'legacyEncoder',
        ];

        yield 'legacyPassword' => [
            static fn (UserEntity $user) => $user->setLegacyPassword('hash'),
            static fn (UserEntity $user) => $user->getLegacyPassword(),
            'hash',
            'legacyPassword',
        ];
    }

    private function userWithInternalProperties(): UserEntity
    {
        $user = new UserEntity();
        $user->internalSetEntityData(
            UserDefinition::ENTITY_NAME,
            new FieldVisibility(['password', 'newsletterChannelIds', 'legacyEncoder', 'legacyPassword']),
        );

        return $user;
    }
}
