<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(MailTemplateTypeDefinition::class)]
class MailTemplateTypeDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(MailTemplateTypeDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(MailTemplateTypeEntity::class, $definition->getEntityClass());
        static::assertSame(MailTemplateTypeCollection::class, $definition->getCollectionClass());
    }

    public function testDeprecatedTemplateDataFieldIsGone(): void
    {
        static::assertNull($this->createDefinition()->getFields()->get('templateData'));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedTemplateDataFieldStillExistsInTheLegacyMajor(): void
    {
        $field = $this->createDefinition()->getFields()->get('templateData');

        static::assertInstanceOf(JsonField::class, $field);
    }

    private function createDefinition(): MailTemplateTypeDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [MailTemplateTypeDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(MailTemplateTypeDefinition::ENTITY_NAME);
        static::assertInstanceOf(MailTemplateTypeDefinition::class, $definition);

        return $definition;
    }
}
