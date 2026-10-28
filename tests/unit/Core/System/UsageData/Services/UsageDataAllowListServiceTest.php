<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\UsageData\Services\UsageDataAllowListService;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(UsageDataAllowListService::class)]
class UsageDataAllowListServiceTest extends TestCase
{
    public function testGetDefaultUsageDataAllowList(): void
    {
        $list = UsageDataAllowListService::getDefaultUsageDataAllowList();

        static::assertNotCount(0, $list);
    }

    public function testItFiltersEntity(): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')
            ->willReturn('not_allowed_entity');

        $service = new UsageDataAllowListService();
        $selectedFields = $service->getFieldsToSelectFromDefinition($definition);

        static::assertCount(0, $selectedFields);
    }

    public function testItFiltersFields(): void
    {
        $definition = new ProductMockEntityDefinition();

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $fields = new FieldCollection([
            new StringField('not_allowed', 'not_in_usage_data_allow_list'),
        ]);

        $definition->setFields(new CompiledFieldCollection($registry, $fields));

        $service = new UsageDataAllowListService();
        $selectedFields = $service->getFieldsToSelectFromDefinition($definition);

        static::assertCount(0, $selectedFields);
    }

    public function testItAddsFields(): void
    {
        $fields = new FieldCollection([
            new StringField('id', 'id'),
        ]);

        $definition = new ProductMockEntityDefinition();

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition->setFields(new CompiledFieldCollection($registry, $fields));

        $service = new UsageDataAllowListService();
        $selectedFields = $service->getFieldsToSelectFromDefinition($definition);

        static::assertCount(1, $selectedFields);
    }

    public function testEntityIsNotAllowed(): void
    {
        $service = new UsageDataAllowListService();
        static::assertFalse($service->isEntityAllowed('not_allowed'));
    }

    public function testEntityIsAllowed(): void
    {
        $service = new UsageDataAllowListService();
        static::assertTrue($service->isEntityAllowed('product'));
    }
}

/**
 * @internal
 */
class ProductMockEntityDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product';
    }

    public function setFields(CompiledFieldCollection $fields): void
    {
        $this->fields = $fields;
    }

    protected function defineFields(): FieldCollection
    {
        if ($this->fields !== null) {
            return $this->fields;
        }

        return new FieldCollection();
    }
}
