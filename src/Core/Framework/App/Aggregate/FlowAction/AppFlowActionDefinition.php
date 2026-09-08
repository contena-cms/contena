<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\FlowAction;

use Contena\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Contena\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationDefinition;
use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class AppFlowActionDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_flow_action';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppFlowActionCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppFlowActionEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.10.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of app\'s flow action.'),
            new FkField('app_id', 'appId', AppDefinition::class)->addFlags(new Required())->setDescription('Unique identity of app.'),
            new StringField('name', 'name', 255)->addFlags(new Required())->setDescription('Name of app flow action.'),
            new StringField('badge', 'badge', 255),
            new JsonField('parameters', 'parameters')->setDescription('Parameters that hold data required for the specific action to be executed within flow.'),
            new JsonField('config', 'config')->setDescription('Specifies detailed information about the component.'),
            new JsonField('headers', 'headers')->setDescription('Indicates the header value within the context of app flow action.'),
            new ListField('requirements', 'requirements', StringField::class),
            new BlobField('icon', 'iconRaw'),
            new StringField('icon', 'icon')->addFlags(new WriteProtected(), new Runtime())->setDescription('Icon to identify app flow action.'),
            new StringField('ct_icon', 'swIcon'),
            new StringField('url', 'url')->addFlags(new Required())->setDescription('An URL to app flow action.'),
            new BoolField('delayable', 'delayable'),
            new TranslatedField('label'),
            new TranslatedField('description'),
            new TranslatedField('headline'),
            new TranslatedField('customFields'),
            new TranslationsAssociationField(AppFlowActionTranslationDefinition::class, 'app_flow_action_id')->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class, 'id', false),
            new OneToManyAssociationField('flowSequences', FlowSequenceDefinition::class, 'app_flow_action_id')->addFlags(new CascadeDelete()),
        ]);
    }
}
