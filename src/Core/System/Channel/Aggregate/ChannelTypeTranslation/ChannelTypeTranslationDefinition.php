<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeDefinition;

class ChannelTypeTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'channel_type_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ChannelTypeTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return ChannelTypeTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return ChannelTypeDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name')->addFlags(new Required()),
            new StringField('manufacturer', 'manufacturer'),
            new StringField('description', 'description'),
            new LongTextField('description_long', 'descriptionLong')->addFlags(new ApiAware(), new AllowHtml()),
            new CustomFields(),
        ]);
    }
}
