<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Repository;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ChannelRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private EntityRepository $currencyRepository;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    /**
     * @var EntityRepository<CountryCollection>
     */
    private EntityRepository $countryRepository;

    /**
     * @var EntityRepository<ShippingMethodCollection>
     */
    private EntityRepository $shippingMethodRepository;

    protected function setUp(): void
    {
        $this->channelRepository = static::getContainer()->get('channel.repository');
        $this->currencyRepository = static::getContainer()->get('currency.repository');
        $this->languageRepository = static::getContainer()->get('language.repository');
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->countryRepository = static::getContainer()->get('country.repository');
        $this->shippingMethodRepository = static::getContainer()->get('shipping_method.repository');
    }

    public function testCreateChannelTest(): void
    {
        $channelId = Uuid::randomHex();
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');
        $secretKey = AccessKeyHelper::generateSecretAccessKey();
        $context = Context::createDefaultContext();

        $name = 'Repository test';
        $cover = 'http://example.org/icon1.jpg';
        $icon = 'sw-icon';
        $screenshots = [
            'http://example.org/image.jpg',
            'http://example.org/image2.jpg',
            'http://example.org/image3.jpg',
        ];
        $typeName = 'test type';
        $manufacturer = 'contena';
        $description = 'my description';
        $descriptionLong = 'an even longer description';

        $this->channelRepository->upsert([[
            'id' => $channelId,
            'name' => $name,
            'type' => [
                'coverUrl' => $cover,
                'iconName' => $icon,
                'screenshotUrls' => $screenshots,
                'name' => $typeName,
                'manufacturer' => $manufacturer,
                'description' => $description,
                'descriptionLong' => $descriptionLong,
            ],
            'accessKey' => $accessKey,
            'secretAccessKey' => $secretKey,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ]], $context);

        $criteria1 = new Criteria([$channelId]);
        $criteria1->addAssociation('type');

        $channel = $this->channelRepository->search($criteria1, $context)->getEntities()->get($channelId);

        static::assertInstanceOf(ChannelEntity::class, $channel);
        static::assertSame($name, $channel->getName());
        static::assertSame($accessKey, $channel->getAccessKey());

        static::assertInstanceOf(ChannelTypeEntity::class, $channel->getType());
        static::assertSame($cover, $channel->getType()->getCoverUrl());
        static::assertSame($icon, $channel->getType()->getIconName());
        static::assertSame($screenshots, $channel->getType()->getScreenshotUrls());
        static::assertSame($typeName, $channel->getType()->getName());
        static::assertSame($manufacturer, $channel->getType()->getManufacturer());
        static::assertSame($description, $channel->getType()->getDescription());
        static::assertSame($descriptionLong, $channel->getType()->getDescriptionLong());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('currency.channels.id', $channelId));
        $currency = $this->currencyRepository->search($criteria, $context)->getEntities();
        static::assertCount(1, $currency);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.channels.id', $channelId));
        $language = $this->languageRepository->search($criteria, $context)->getEntities();
        static::assertCount(1, $language);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('payment_method.channels.id', $channelId));
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context)->getEntities();
        static::assertCount(1, $paymentMethod);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('country.channels.id', $channelId));
        $country = $this->countryRepository->search($criteria, $context)->getEntities();
        static::assertCount(1, $country);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shipping_method.channels.id', $channelId));
        $shippingMethod = $this->shippingMethodRepository->search($criteria, $context)->getEntities();
        static::assertCount(1, $shippingMethod);
    }

    public function testTaxCalculationDefault(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(),
            'accessKey' => $id,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
        ];

        $this->channelRepository->create([$data], Context::createDefaultContext());

        /** @var ChannelEntity $channel */
        $channel = $this->channelRepository
            ->search(new Criteria([$id]), Context::createDefaultContext())->getEntities()
            ->first();

        static::assertSame(ChannelDefinition::CALCULATION_TYPE_HORIZONTAL, $channel->getTaxCalculationType());
    }
}
