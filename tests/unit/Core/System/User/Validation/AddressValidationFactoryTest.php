<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Validation\Constraint\NoHtml;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Validation\AddressValidationFactory;
use Contena\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[CoversClass(AddressValidationFactory::class)]
class AddressValidationFactoryTest extends TestCase
{
    private AddressValidationFactory $addressValidationFactory;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $systemConfigServiceMock = static::createStub(SystemConfigService::class);

        $this->addressValidationFactory = new AddressValidationFactory($systemConfigServiceMock);

        $user = new UserEntity();

        $country = new CountryEntity();

        $address = new UserAddressEntity();
        $address->setId('foo');
        $address->setCountryId('foo');
        $address->setCountry($country);

        $user->setActiveShippingAddress($address);
        $user->setActiveBillingAddress($address);

        $this->channelContext = Generator::generateChannelContext(user: $user);
    }

    public function testDefinitionRulesCreate(): void
    {
        $definition = $this->addressValidationFactory->create($this->channelContext)->getProperties();

        $this->assertAddressDefinition($definition);

        static::assertCount(13, $definition);
    }

    public function testDefinitionRulesUpdate(): void
    {
        $definition = $this->addressValidationFactory->update($this->channelContext)->getProperties();

        static::assertCount(14, $definition);
        static::assertArrayHasKey('id', $definition);

        static::assertCount(2, $definition['id']);
        static::assertInstanceOf(NotBlank::class, $definition['id'][0]);
        static::assertInstanceOf(EntityExists::class, $definition['id'][1]);

        $this->assertAddressDefinition($definition);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function assertAddressDefinition(array $definition): void
    {
        static::assertArrayHasKey('title', $definition);
        static::assertCount(2, $definition['title']);
        static::assertInstanceOf(Length::class, $definition['title'][0]);
        static::assertInstanceOf(NoHtml::class, $definition['title'][1]);
        static::assertArrayHasKey('zipcode', $definition);
        static::assertInstanceOf(Length::class, $definition['zipcode'][0]);
        static::assertCount(3, $definition['firstName']);
        static::assertInstanceOf(NotBlank::class, $definition['firstName'][0]);
        static::assertInstanceOf(Length::class, $definition['firstName'][1]);
        static::assertInstanceOf(NoHtml::class, $definition['firstName'][2]);
        static::assertCount(3, $definition['lastName']);
        static::assertInstanceOf(NotBlank::class, $definition['lastName'][0]);
        static::assertInstanceOf(Length::class, $definition['lastName'][1]);
        static::assertInstanceOf(NoHtml::class, $definition['lastName'][2]);

        static::assertArrayHasKey('salutationId', $definition);
        static::assertArrayHasKey('countryId', $definition);
        static::assertArrayHasKey('countryStateId', $definition);
        static::assertArrayHasKey('firstName', $definition);
        static::assertArrayHasKey('lastName', $definition);
        static::assertArrayHasKey('street', $definition);
        static::assertArrayHasKey('city', $definition);

        static::assertCount(1, $definition['salutationId']);
        static::assertInstanceOf(EntityExists::class, $definition['salutationId'][0]);

        static::assertCount(3, $definition['countryId']);
        static::assertInstanceOf(EntityExists::class, $definition['countryId'][0]);
        static::assertInstanceOf(NotBlank::class, $definition['countryId'][1]);
        static::assertInstanceOf(EntityExists::class, $definition['countryId'][2]);

        static::assertCount(1, $definition['countryStateId']);
        static::assertInstanceOf(EntityExists::class, $definition['countryStateId'][0]);

        static::assertCount(2, $definition['city']);
        static::assertInstanceOf(NotBlank::class, $definition['city'][0]);
        static::assertInstanceOf(NoHtml::class, $definition['city'][1]);

        static::assertCount(2, $definition['street']);
        static::assertInstanceOf(NotBlank::class, $definition['street'][0]);
        static::assertInstanceOf(NoHtml::class, $definition['street'][1]);

        foreach (['company', 'department', 'additionalAddressLine1', 'additionalAddressLine2'] as $freeTextField) {
            static::assertArrayHasKey($freeTextField, $definition);
            static::assertCount(1, $definition[$freeTextField]);
            static::assertInstanceOf(NoHtml::class, $definition[$freeTextField][0]);
        }
    }
}
