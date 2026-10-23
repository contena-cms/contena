<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter;

use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @codeCoverageIgnore
 */
class ContentStreamFilterDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'content_stream_filter';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContentStreamFilterEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContentStreamFilterCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return ContentStreamFilterHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return ContentStreamDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of blog stream filter.'),
            (new FkField('content_stream_id', 'contentStreamId', ContentStreamDefinition::class))->addFlags(new Required())->setDescription('Unique identity of blog stream.'),
            new ParentFkField(self::class),
            (new StringField('type', 'type'))->addFlags(new Required())->setDescription('Type is either `static` or `stream`.'),
            (new StringField('field', 'field'))->setDescription('Filter by field.'),
            (new StringField('operator', 'operator'))->setDescription('Filter by operator like >, ==, < , >=, etc.'),
            (new LongTextField('value', 'value'))->setDescription('Filter by ContentStreamFilter value.'),
            (new JsonField('parameters', 'parameters'))->setDescription('Additional parameters, such as specifying a date starting from a particular point.'),
            (new IntField('position', 'position'))->setDescription('The order of the tabs of your defined blog stream filter to be displayed.'),
            new ManyToOneAssociationField('contentStream', 'content_stream_id', ContentStreamDefinition::class, 'id', false),
            (new ParentAssociationField(self::class, 'id'))->setDescription('Unique identity of blog stream filter.'),
            new ChildrenAssociationField(self::class, 'queries'),
            (new CustomFields())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}
