<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelType;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation\ChannelTypeTranslationDefinition;
use Contena\Core\System\Channel\ChannelDefinition;

class ChannelTypeDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'channel_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ChannelTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return ChannelTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of channel type.'),
            new StringField('cover_url', 'coverUrl')->setDescription('A url for the channel type.'),
            new StringField('icon_name', 'iconName')->setDescription('An icon for channel type.'),
            new ListField('screenshot_urls', 'screenshotUrls', StringField::class),
            new TranslatedField('name'),
            new TranslatedField('manufacturer'),
            new TranslatedField('description'),
            new TranslatedField('descriptionLong'),
            new TranslatedField('customFields'),
            new TranslationsAssociationField(ChannelTypeTranslationDefinition::class, 'channel_type_id')->addFlags(new Required()),
            new OneToManyAssociationField('channels', ChannelDefinition::class, 'type_id', 'id'),
        ]);
    }
}
