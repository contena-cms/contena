<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Template;

use Contena\Core\Framework\App\Template\TemplateCollection;
use Contena\Core\Framework\App\Template\TemplateDefinition;
use Contena\Core\Framework\App\Template\TemplateEntity;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(TemplateDefinition::class)]
class TemplateDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(TemplateDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(TemplateEntity::class, $definition->getEntityClass());
        static::assertSame(TemplateCollection::class, $definition->getCollectionClass());
        static::assertSame('6.3.1.0', $definition->since());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): TemplateDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [TemplateDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(TemplateDefinition::ENTITY_NAME);
        static::assertInstanceOf(TemplateDefinition::class, $definition);

        return $definition;
    }
}
