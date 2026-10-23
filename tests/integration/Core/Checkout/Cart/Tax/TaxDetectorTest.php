<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Checkout\Cart\Tax\TaxDetector;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class TaxDetectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUseGrossPrices(): void
    {
        $context = $this->createMock(ChannelContext::class);
        $userGroup = new UserGroupEntity();
        $userGroup->setDisplayGross(true);
        $context->expects($this->once())->method('getCurrentUserGroup')->willReturn($userGroup);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->useGross($context));
    }

    public function testDoNotUseGrossPrices(): void
    {
        $context = $this->createMock(ChannelContext::class);
        $userGroup = new UserGroupEntity();
        $userGroup->setDisplayGross(false);
        $context->expects($this->once())->method('getCurrentUserGroup')->willReturn($userGroup);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->useGross($context));
    }

    public function testIsNetDelivery(): void
    {
        $context = $this->createMock(ChannelContext::class);

        $country = new CountryEntity();

        $country->setUserTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));
        $country->setCompanyTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTax(): void
    {
        $context = $this->createMock(ChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);
        $data = [
            'id' => $country->getId(),
            'userTax' => [
                'enabled' => false,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'companyTax' => [
                'enabled' => true,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'vatIdPattern' => '(DE)?[0-9]{9}',
        ];

        $countryRepository->update([$data], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);

        $user = new UserEntity();
        $user->setCompany('ABC Company');
        $user->setVatIds(['DE123123123']);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $context->expects($this->once())->method('getUser')->willReturn(
            $user
        );

        $taxDetector = static::getContainer()->get(TaxDetector::class);

        static::assertTrue($taxDetector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTax(): void
    {
        $context = $this->createMock(ChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);
        $countryRepository->update([
            [
                'id' => $country->getId(),
                'taxFree' => false,
                'companyTaxFree' => false,
                'vatIdPattern' => '(DE)?[0-9]{9}',
                'checkVatIdPattern' => false,
            ],
        ], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTaxAndWrongVatIdPattern(): void
    {
        $context = $this->createMock(ChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($deCountry);
        $data = [
            'id' => $deCountry->getId(),
            'userTax' => [
                'enabled' => false,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'companyTax' => [
                'enabled' => true,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ];

        $countryRepository->update([$data], Context::createDefaultContext());
        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($deCountry);

        $user = new UserEntity();
        $user->setCompany('ABC Company');
        $user->setVatIds(['VN123123']);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($deCountry)
        );

        $context->expects($this->once())->method('getUser')->willReturn(
            $user
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTaxAndNullVatId(): void
    {
        $context = $this->createMock(ChannelContext::class);

        $country = (new CountryEntity())->assign([
            'userTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '...',
            'checkVatIdPattern' => true,
        ]);

        $user = (new UserEntity())->assign([
            'company' => 'ABC Compay',
            'vatIds' => [null],
        ]);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $context->expects($this->once())->method('getUser')->willReturn(
            $user
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTaxAndWrongVatIdButVatIdCheckDisabled(): void
    {
        $context = $this->createMock(ChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($deCountry);
        $data = [
            'id' => $deCountry->getId(),
            'userTax' => [
                'enabled' => false,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'companyTax' => [
                'enabled' => true,
                'currencyId' => Defaults::CURRENCY,
                'amount' => 0,
            ],
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => false,
        ];

        $countryRepository->update([$data], Context::createDefaultContext());
        $deCountry = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($deCountry);

        $user = new UserEntity();
        $user->setCompany('ABC Company');
        $user->setVatIds(['VN123123']);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($deCountry)
        );

        $context->expects($this->once())->method('getUser')->willReturn(
            $user
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTaxAndNonEuCountry(): void
    {
        $context = $this->createMock(ChannelContext::class);

        $country = (new CountryEntity())->assign([
            'userTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => false,
        ]);

        $user = (new UserEntity())->assign([
            'company' => 'Non-EU Company',
            'vatIds' => ['ANY-VAT-ID'],
        ]);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );
        $context->expects($this->once())->method('getUser')->willReturn($user);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTaxAndEuCountryWithEmptyVatIdPattern(): void
    {
        $context = $this->createMock(ChannelContext::class);

        $country = (new CountryEntity())->assign([
            'userTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => null,
            'checkVatIdPattern' => true,
        ]);

        $user = (new UserEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ['DE123456789'],
        ]);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );
        $context->expects($this->once())->method('getUser')->willReturn($user);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTaxAndEuCountryWithEmptyStringVatIdPattern(): void
    {
        $context = $this->createMock(ChannelContext::class);

        $country = (new CountryEntity())->assign([
            'userTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '',
            'checkVatIdPattern' => true,
        ]);

        $user = (new UserEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ['FR12345678901'],
        ]);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );
        $context->expects($this->once())->method('getUser')->willReturn($user);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNetDeliveryWithCompanyFreeTaxAndEuCountryWithValidVatIdMatchingPattern(): void
    {
        $context = $this->createMock(ChannelContext::class);

        $country = (new CountryEntity())->assign([
            'userTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $user = (new UserEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ['DE123456789'],
        ]);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );
        $context->expects($this->once())->method('getUser')->willReturn($user);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNotNetDeliveryWithCompanyFreeTaxAndEuCountryWithInvalidVatIdPattern(): void
    {
        $context = $this->createMock(ChannelContext::class);

        $country = (new CountryEntity())->assign([
            'userTax' => new TaxFreeConfig(false),
            'companyTax' => new TaxFreeConfig(true),
            'isEu' => true,
            'vatIdPattern' => '(DE)?[0-9]{9}',
            'checkVatIdPattern' => true,
        ]);

        $user = (new UserEntity())->assign([
            'company' => 'EU Company',
            'vatIds' => ['INVALID-VAT'],
        ]);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );
        $context->expects($this->once())->method('getUser')->willReturn($user);

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }

    public function testIsNotNetDelivery(): void
    {
        $context = $this->createMock(ChannelContext::class);

        /** @var EntityRepository<CountryCollection> $countryRepository */
        $countryRepository = static::getContainer()->get('country.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', 'DE'));
        $criteria->setLimit(1);

        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);
        $countryRepository->update([
            [
                'id' => $country->getId(),
                'taxFree' => false,
                'companyTaxFree' => false,
                'vatIdPattern' => '(DE)?[0-9]{9}',
                'checkVatIdPattern' => false,
            ],
        ], Context::createDefaultContext());
        $country = $countryRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($country);

        $context->expects($this->once())->method('getShippingLocation')->willReturn(
            ShippingLocation::createFromCountry($country)
        );

        $detector = static::getContainer()->get(TaxDetector::class);
        static::assertFalse($detector->isNetDelivery($context));
    }
}
