<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\System\Member\Validation\AddressValidationFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
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

    protected function setUp(): void
    {
        $this->addressValidationFactory = new AddressValidationFactory(
            static::createStub(SystemConfigService::class)
        );
    }

    public function testDefinitionRulesCreate(): void
    {
        $definition = $this->addressValidationFactory->create(Generator::generateChannelContext())->getProperties();

        $this->assertAddressDefinition($definition);

        static::assertCount(8, $definition);
    }

    public function testDefinitionRulesUpdate(): void
    {
        $definition = $this->addressValidationFactory->update(Generator::generateChannelContext())->getProperties();

        static::assertCount(9, $definition);
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
        static::assertInstanceOf(Length::class, $definition['title'][0]);
        static::assertArrayHasKey('zipcode', $definition);
        static::assertInstanceOf(Length::class, $definition['zipcode'][0]);
        static::assertCount(2, $definition['firstName']);
        static::assertInstanceOf(NotBlank::class, $definition['firstName'][0]);
        static::assertInstanceOf(Length::class, $definition['firstName'][1]);
        static::assertCount(2, $definition['lastName']);
        static::assertInstanceOf(NotBlank::class, $definition['lastName'][0]);
        static::assertInstanceOf(Length::class, $definition['lastName'][1]);

        static::assertArrayHasKey('countryId', $definition);
        static::assertArrayHasKey('regionId', $definition);
        static::assertArrayHasKey('firstName', $definition);
        static::assertArrayHasKey('lastName', $definition);
        static::assertArrayHasKey('street', $definition);
        static::assertArrayHasKey('city', $definition);

        static::assertCount(3, $definition['countryId']);
        static::assertInstanceOf(EntityExists::class, $definition['countryId'][0]);
        static::assertInstanceOf(NotBlank::class, $definition['countryId'][1]);
        static::assertInstanceOf(EntityExists::class, $definition['countryId'][2]);

        static::assertCount(1, $definition['regionId']);
        static::assertInstanceOf(EntityExists::class, $definition['regionId'][0]);

        static::assertCount(1, $definition['city']);
        static::assertInstanceOf(NotBlank::class, $definition['city'][0]);

        static::assertCount(1, $definition['street']);
        static::assertInstanceOf(NotBlank::class, $definition['street'][0]);
    }
}
