<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Aggregate\SnippetSet;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Snippet\SnippetDefinition;

class SnippetSetDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'snippet_set';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SnippetSetCollection::class;
    }

    public function getEntityClass(): string
    {
        return SnippetSetEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of snippet set.'),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required())->setDescription('Name of snippet set.'),
            new StringField('base_file', 'baseFile')->addFlags(new Required()),
            new StringField('iso', 'iso')->addFlags(new ApiAware(), new Required())->setDescription('ISO nomenclature used to classify languages.'),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new OneToManyAssociationField('snippets', SnippetDefinition::class, 'snippet_set_id')->addFlags(new ApiAware(), new CascadeDelete()),
            new OneToManyAssociationField('channelDomains', ChannelDomainDefinition::class, 'snippet_set_id')->addFlags(new RestrictDelete()),
        ]);
    }
}
