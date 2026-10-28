<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Aggregate\SalesChannelType;

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
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelTypeDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'sales_channel_type';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelTypeCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalesChannelTypeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of sales channel type.'),
            (new StringField('cover_url', 'coverUrl'))->setDescription('A url for the sales channel type.'),
            (new StringField('icon_name', 'iconName'))->setDescription('An icon for sales channel type.'),
            new ListField('screenshot_urls', 'screenshotUrls', StringField::class),
            new TranslatedField('name'),
            new TranslatedField('manufacturer'),
            new TranslatedField('description'),
            new TranslatedField('descriptionLong'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(SalesChannelTypeTranslationDefinition::class, 'sales_channel_type_id'))->addFlags(new Required()),
            new OneToManyAssociationField('salesChannels', SalesChannelDefinition::class, 'type_id', 'id'),
        ]);
    }
}
