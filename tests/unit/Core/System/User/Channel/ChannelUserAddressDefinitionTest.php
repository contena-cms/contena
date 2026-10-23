<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Channel\ChannelUserAddressCollection;
use Contena\Core\System\User\Channel\ChannelUserAddressDefinition;
use Contena\Core\System\User\Channel\ChannelUserAddressEntity;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelUserAddressDefinition::class)]
class ChannelUserAddressDefinitionTest extends TestCase
{
    public function testEntityAndCollectionClasses(): void
    {
        $definition = new ChannelUserAddressDefinition();

        static::assertSame(ChannelUserAddressEntity::class, $definition->getEntityClass());
        static::assertSame(ChannelUserAddressCollection::class, $definition->getCollectionClass());
    }

    public function testProcessCriteriaWithoutUserFiltersOnNull(): void
    {
        $criteria = new Criteria();
        $context = Generator::generateChannelContext(overrides: ['user' => null]);

        (new ChannelUserAddressDefinition())->processCriteria($criteria, $context);

        static::assertEquals([new EqualsFilter('userId', null)], $criteria->getFilters());
    }

    public function testProcessCriteria(): void
    {
        $definition = new ChannelUserAddressDefinition();
        $criteria = new Criteria();
        $context = Generator::generateChannelContext();

        $definition->processCriteria($criteria, $context);

        static::assertNotCount(0, $criteria->getFilters());

        $filter = $criteria->getFilters()[0] ?? null;
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame('userId', $filter->getField());
        static::assertSame($context->getUser()?->getId(), $filter->getValue());
    }

    public function testDefineFields(): void
    {
        $definition = new ChannelUserAddressDefinition();

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition->compile($registry);
        $fields = $definition->getFields();

        $billingField = $fields->get('isDefaultBillingAddress');
        static::assertInstanceOf(BoolField::class, $billingField);
        static::assertTrue($billingField->is(Runtime::class));
        static::assertTrue($billingField->is(ApiAware::class));

        $shippingField = $fields->get('isDefaultShippingAddress');
        static::assertInstanceOf(BoolField::class, $shippingField);
        static::assertTrue($shippingField->is(Runtime::class));
        static::assertTrue($shippingField->is(ApiAware::class));
    }
}
