<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\FlowEvent;

use Contena\Core\Content\Flow\FlowDefinition;
use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class AppFlowEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'app_flow_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppFlowEventCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppFlowEventEntity::class;
    }

    public function since(): ?string
    {
        return '6.5.2.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of app flow event.'),
            new FkField('app_id', 'appId', AppDefinition::class)->addFlags(new Required())->setDescription('Unique identity of app.'),
            new StringField('name', 'name', 255)->addFlags(new Required())->setDescription('Unique name of the AppFlowEvent.'),
            new ListField('aware', 'aware', StringField::class)->addFlags(new Required())->setDescription('Parameter that indicates the areas in which the app flow event is supported.'),
            new CustomFields()->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class, 'id', false),
            new OneToManyAssociationField('flows', FlowDefinition::class, 'app_flow_event_id')->addFlags(new CascadeDelete()),
        ]);
    }
}
