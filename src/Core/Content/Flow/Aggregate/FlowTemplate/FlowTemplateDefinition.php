<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Aggregate\FlowTemplate;

use Contena\Core\Content\Flow\DataAbstractionLayer\Field\FlowTemplateConfigField;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class FlowTemplateDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'flow_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return FlowTemplateCollection::class;
    }

    public function getEntityClass(): string
    {
        return FlowTemplateEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name', 255)->addFlags(new Required()),
            new FlowTemplateConfigField('config', 'config'),
        ]);
    }
}
