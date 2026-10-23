<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Tax\TaxRuleType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Contena\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;
use Contena\Core\System\Tax\TaxRuleType\EntireCountryRuleTypeFilter;

/**
 * @internal
 */
#[CoversClass(EntireCountryRuleTypeFilter::class)]
class EntireCountryRuleTypeFilterTest extends TestCase
{
    public function testMatchesNotWithWrongType(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName('not me');

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);

        $filter = new EntireCountryRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation(new CountryEntity(), null, null)));
    }

    public function testMatchesNotWithWrongCountry(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('other-country-id');

        $filter = new EntireCountryRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation($this->getCountry(), null, null)));
    }

    public function testMatchesNotWithFutureDate(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2040-01-01'));
        $rule->setType($type);
        $rule->setCountryId('country-id');

        $filter = new EntireCountryRuleTypeFilter();
        static::assertFalse($filter->match($rule, null, new ShippingLocation($this->getCountry(), null, null)));
    }

    public function testMatchesWithPastDate(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setActiveFrom(new \DateTime('2020-01-01'));
        $rule->setType($type);
        $rule->setCountryId('country-id');

        $filter = new EntireCountryRuleTypeFilter();
        static::assertTrue($filter->match($rule, null, new ShippingLocation($this->getCountry(), null, null)));
    }

    public function testMatches(): void
    {
        $type = new TaxRuleTypeEntity();
        $type->setTechnicalName(EntireCountryRuleTypeFilter::TECHNICAL_NAME);

        $rule = new TaxRuleEntity();
        $rule->setType($type);
        $rule->setCountryId('country-id');

        $filter = new EntireCountryRuleTypeFilter();
        static::assertTrue($filter->match($rule, null, new ShippingLocation($this->getCountry(), null, null)));
    }

    private function getCountry(): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId('country-id');

        return $country;
    }
}
