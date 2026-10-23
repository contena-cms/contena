<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionChannel\PromotionChannelCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\LandingPage\LandingPageCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityCollection;
use Contena\Core\Content\BlogExport\BlogExportCollection;
use Contena\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Contena\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeChannel\NumberRangeChannelCollection;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsEntity;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileCollection;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationCollection;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigCollection;

/**
 * @internal
 */
#[CoversClass(ChannelEntity::class)]
class ChannelEntityTest extends TestCase
{
    public function testGetSetMaintenanceIpAllowlist(): void
    {
        $entity = new ChannelEntity();
        static::assertNull($entity->getMaintenanceIpAllowlist());

        $entity->setMaintenanceIpAllowlist(['127.0.0.1', '::1']);

        static::assertSame(['127.0.0.1', '::1'], $entity->getMaintenanceIpAllowlist());
    }

    public function testDeprecatedGetterReturnsAllowlistValue(): void
    {
        $entity = new ChannelEntity();
        $entity->setMaintenanceIpAllowlist(['192.168.0.1']);

        $result = Feature::silent('v6.8.0.0', fn (): ?array => $entity->getMaintenanceIpWhitelist());

        static::assertSame(['192.168.0.1'], $result);
    }

    public function testDeprecatedSetterUpdatesAllowlist(): void
    {
        $entity = new ChannelEntity();

        Feature::silent('v6.8.0.0', function () use ($entity): void {
            $entity->setMaintenanceIpWhitelist(['10.0.0.1']);
        });

        static::assertSame(['10.0.0.1'], $entity->getMaintenanceIpAllowlist());
    }

    public function testDeprecatedGetterThrowsWhenMajorIsActive(): void
    {
        $this->expectException(\Throwable::class);

        (new ChannelEntity())->getMaintenanceIpWhitelist();
    }

    public function testScalarAccessorsRoundTrip(): void
    {
        $channel = new ChannelEntity();

        $channel->setMailHeaderFooterId('mail-header-footer-id');
        $channel->setLanguageId('language-id');
        $channel->setCurrencyId('currency-id');
        $channel->setPaymentMethodId('payment-method-id');
        $channel->setShippingMethodId('shipping-method-id');
        $channel->setCountryId('country-id');
        $channel->setName('name');
        $channel->setShortName('short-name');
        $channel->setAccessKey('access-key');
        $channel->setConfiguration(['configuration']);
        $channel->setActive(true);
        $channel->setMaintenance(true);
        $channel->setTypeId('type-id');
        $channel->setNavigationCategoryId('navigation-category-id');
        $channel->setHomeSlotConfig(['slot-id' => ['content' => 'home']]);
        $channel->setHomeCmsPageId('home-cms-page-id');
        $channel->setHomeEnabled(true);
        $channel->setHomeName('home-name');
        $channel->setHomeMetaTitle('home-meta-title');
        $channel->setHomeMetaDescription('home-meta-description');
        $channel->setHomeKeywords('home-keywords');
        $channel->setUserGroupId('user-group-id');
        $channel->setFooterCategoryId('footer-category-id');
        $channel->setServiceCategoryId('service-category-id');
        $channel->setPaymentMethodIds(['paymentMethodIds']);
        $channel->setNavigationCategoryDepth(3);
        $channel->setHreflangActive(true);
        $channel->setHreflangDefaultDomainId('hreflang-default-domain-id');
        $channel->setAnalyticsId('analytics-id');
        $channel->setTaxCalculationType('tax-calculation-type');
        $channel->setNavigationCategoryVersionId('navigation-category-version-id');
        $channel->setHomeCmsPageVersionId('home-cms-page-version-id');
        $channel->setFooterCategoryVersionId('footer-category-version-id');
        $channel->setServiceCategoryVersionId('service-category-version-id');
        $channel->setBusinessTimeZone('business-time-zone');

        static::assertSame('mail-header-footer-id', $channel->getMailHeaderFooterId());
        static::assertSame('language-id', $channel->getLanguageId());
        static::assertSame('currency-id', $channel->getCurrencyId());
        static::assertSame('payment-method-id', $channel->getPaymentMethodId());
        static::assertSame('shipping-method-id', $channel->getShippingMethodId());
        static::assertSame('country-id', $channel->getCountryId());
        static::assertSame('name', $channel->getName());
        static::assertSame('short-name', $channel->getShortName());
        static::assertSame('access-key', $channel->getAccessKey());
        static::assertSame(['configuration'], $channel->getConfiguration());
        static::assertTrue($channel->getActive());
        static::assertTrue($channel->isMaintenance());
        static::assertSame('type-id', $channel->getTypeId());
        static::assertSame('navigation-category-id', $channel->getNavigationCategoryId());
        static::assertSame(['slot-id' => ['content' => 'home']], $channel->getHomeSlotConfig());
        static::assertSame('home-cms-page-id', $channel->getHomeCmsPageId());
        static::assertTrue($channel->getHomeEnabled());
        static::assertSame('home-name', $channel->getHomeName());
        static::assertSame('home-meta-title', $channel->getHomeMetaTitle());
        static::assertSame('home-meta-description', $channel->getHomeMetaDescription());
        static::assertSame('home-keywords', $channel->getHomeKeywords());
        static::assertSame('user-group-id', $channel->getUserGroupId());
        static::assertSame('footer-category-id', $channel->getFooterCategoryId());
        static::assertSame('service-category-id', $channel->getServiceCategoryId());
        static::assertSame(['paymentMethodIds'], $channel->getPaymentMethodIds());
        static::assertSame(3, $channel->getNavigationCategoryDepth());
        static::assertTrue($channel->isHreflangActive());
        static::assertSame('hreflang-default-domain-id', $channel->getHreflangDefaultDomainId());
        static::assertSame('analytics-id', $channel->getAnalyticsId());
        static::assertSame('tax-calculation-type', $channel->getTaxCalculationType());
        static::assertSame('navigation-category-version-id', $channel->getNavigationCategoryVersionId());
        static::assertSame('home-cms-page-version-id', $channel->getHomeCmsPageVersionId());
        static::assertSame('footer-category-version-id', $channel->getFooterCategoryVersionId());
        static::assertSame('service-category-version-id', $channel->getServiceCategoryVersionId());
        static::assertSame('business-time-zone', $channel->getBusinessTimeZone());
    }

    public function testAssociationAccessorsRoundTrip(): void
    {
        $channel = new ChannelEntity();

        $mailHeaderFooter = new MailHeaderFooterEntity();
        $currencies = new CurrencyCollection();
        $languages = new LanguageCollection();
        $currency = new CurrencyEntity();
        $language = new LanguageEntity();
        $paymentMethod = new PaymentMethodEntity();
        $shippingMethod = new ShippingMethodEntity();
        $country = new CountryEntity();
        $orders = new OrderCollection();
        $users = new UserCollection();
        $type = new ChannelTypeEntity();
        $countries = new CountryCollection();
        $translations = new ChannelTranslationCollection();
        $paymentMethods = new PaymentMethodCollection();
        $shippingMethods = new ShippingMethodCollection();
        $domains = new ChannelDomainCollection();
        $systemConfigs = new SystemConfigCollection();
        $navigationCategory = new CategoryEntity();
        $homeCmsPage = new CmsPageEntity();
        $blogVisibilities = new BlogVisibilityCollection();
        $userGroup = new UserGroupEntity();
        $newsletterRecipients = new NewsletterRecipientCollection();
        $promotionChannels = new PromotionChannelCollection();
        $numberRangeChannels = new NumberRangeChannelCollection();
        $footerCategory = new CategoryEntity();
        $serviceCategory = new CategoryEntity();
        $documentBaseConfigChannels = new DocumentBaseConfigDefinition();
        $blogReviews = new BlogReviewCollection();
        $seoUrls = new SeoUrlCollection();
        $seoUrlTemplates = new SeoUrlTemplateCollection();
        $mainCategories = new MainCategoryCollection();
        $blogExports = new BlogExportCollection();
        $channelFiles = new ChannelFileCollection();
        $hreflangDefaultDomain = new ChannelDomainEntity();
        $analytics = new ChannelAnalyticsEntity();
        $userGroupsRegistrations = new UserGroupCollection();
        $boundUsers = new UserCollection();
        $wishlists = new UserWishlistCollection();
        $landingPages = new LandingPageCollection();
        $measurementUnits = new MeasurementUnits('metric', ['length' => 'mm']);

        $channel->setMailHeaderFooter($mailHeaderFooter);
        $channel->setCurrencies($currencies);
        $channel->setLanguages($languages);
        $channel->setCurrency($currency);
        $channel->setLanguage($language);
        $channel->setPaymentMethod($paymentMethod);
        $channel->setShippingMethod($shippingMethod);
        $channel->setCountry($country);
        $channel->setOrders($orders);
        $channel->setUsers($users);
        $channel->setType($type);
        $channel->setCountries($countries);
        $channel->setTranslations($translations);
        $channel->setPaymentMethods($paymentMethods);
        $channel->setShippingMethods($shippingMethods);
        $channel->setDomains($domains);
        $channel->setSystemConfigs($systemConfigs);
        $channel->setNavigationCategory($navigationCategory);
        $channel->setHomeCmsPage($homeCmsPage);
        $channel->setBlogVisibilities($blogVisibilities);
        $channel->setUserGroup($userGroup);
        $channel->setNewsletterRecipients($newsletterRecipients);
        $channel->setPromotionChannels($promotionChannels);
        $channel->setNumberRangeChannels($numberRangeChannels);
        $channel->setFooterCategory($footerCategory);
        $channel->setServiceCategory($serviceCategory);
        $channel->setDocumentBaseConfigChannels($documentBaseConfigChannels);
        $channel->setBlogReviews($blogReviews);
        $channel->setSeoUrls($seoUrls);
        $channel->setSeoUrlTemplates($seoUrlTemplates);
        $channel->setMainCategories($mainCategories);
        $channel->setBlogExports($blogExports);
        $channel->setChannelFiles($channelFiles);
        $channel->setHreflangDefaultDomain($hreflangDefaultDomain);
        $channel->setAnalytics($analytics);
        $channel->setUserGroupsRegistrations($userGroupsRegistrations);
        $channel->setBoundUsers($boundUsers);
        $channel->setWishlists($wishlists);
        $channel->setLandingPages($landingPages);
        $channel->setMeasurementUnits($measurementUnits);

        static::assertSame($mailHeaderFooter, $channel->getMailHeaderFooter());
        static::assertSame($currencies, $channel->getCurrencies());
        static::assertSame($languages, $channel->getLanguages());
        static::assertSame($currency, $channel->getCurrency());
        static::assertSame($language, $channel->getLanguage());
        static::assertSame($paymentMethod, $channel->getPaymentMethod());
        static::assertSame($shippingMethod, $channel->getShippingMethod());
        static::assertSame($country, $channel->getCountry());
        static::assertSame($orders, $channel->getOrders());
        static::assertSame($users, $channel->getUsers());
        static::assertSame($type, $channel->getType());
        static::assertSame($countries, $channel->getCountries());
        static::assertSame($translations, $channel->getTranslations());
        static::assertSame($paymentMethods, $channel->getPaymentMethods());
        static::assertSame($shippingMethods, $channel->getShippingMethods());
        static::assertSame($domains, $channel->getDomains());
        static::assertSame($systemConfigs, $channel->getSystemConfigs());
        static::assertSame($navigationCategory, $channel->getNavigationCategory());
        static::assertSame($homeCmsPage, $channel->getHomeCmsPage());
        static::assertSame($blogVisibilities, $channel->getBlogVisibilities());
        static::assertSame($userGroup, $channel->getUserGroup());
        static::assertSame($newsletterRecipients, $channel->getNewsletterRecipients());
        static::assertSame($promotionChannels, $channel->getPromotionChannels());
        static::assertSame($numberRangeChannels, $channel->getNumberRangeChannels());
        static::assertSame($footerCategory, $channel->getFooterCategory());
        static::assertSame($serviceCategory, $channel->getServiceCategory());
        static::assertSame($documentBaseConfigChannels, $channel->getDocumentBaseConfigChannels());
        static::assertSame($blogReviews, $channel->getBlogReviews());
        static::assertSame($seoUrls, $channel->getSeoUrls());
        static::assertSame($seoUrlTemplates, $channel->getSeoUrlTemplates());
        static::assertSame($mainCategories, $channel->getMainCategories());
        static::assertSame($blogExports, $channel->getBlogExports());
        static::assertSame($channelFiles, $channel->getChannelFiles());
        static::assertSame($hreflangDefaultDomain, $channel->getHreflangDefaultDomain());
        static::assertSame($analytics, $channel->getAnalytics());
        static::assertSame($userGroupsRegistrations, $channel->getUserGroupsRegistrations());
        static::assertSame($boundUsers, $channel->getBoundUsers());
        static::assertSame($wishlists, $channel->getWishlists());
        static::assertSame($landingPages, $channel->getLandingPages());
        static::assertSame($measurementUnits, $channel->getMeasurementUnits());
    }
}
