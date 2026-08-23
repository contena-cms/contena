<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrl;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Language\LanguageDefinition;

class SeoUrlDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'seo_url';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SeoUrlCollection::class;
    }

    public function getEntityClass(): string
    {
        return SeoUrlEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of Seo Url.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of channel.'),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of language.'),
            new IdField('foreign_key', 'foreignKey')->addFlags(new ApiAware(), new Required())->setDescription('The key that references to blog or category entity ID.'),

            new StringField('route_name', 'routeName', 50)->addFlags(new ApiAware(), new Required())->setDescription('A destination routeName that has been registered somewhere in the app\'s router. For example: \\\"frontend.detail.page\\\"'),
            new StringField('path_info', 'pathInfo', 750)->addFlags(new ApiAware(), new Required())->setDescription('Path to blog URL. For example: \\\"/detail/bbf36734504741c79a3bbe3795b91564\\\"'),
            new StringField('seo_path_info', 'seoPathInfo', 750)->addFlags(new ApiAware(), new Required())->setDescription('Seo path to blog. For example: \\\"Pepper-white-ground-pearl/SW10098\\\"'),
            new BoolField('is_canonical', 'isCanonical')->addFlags(new ApiAware())->setDescription('When set to true, search redirects to the main URL.'),
            new BoolField('is_modified', 'isModified')->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the seo url is changed.'),
            new BoolField('is_deleted', 'isDeleted')->addFlags(new ApiAware())->setDescription('When set to true, the URL is deleted and cannot be used any more but it is still available on table and can be restored later.'),
            new StringField('error', 'error')->addFlags(new Runtime(), new ApiAware()),

            new StringField('url', 'url')->addFlags(new ApiAware(), new Runtime()),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),

            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
        ]);
    }
}
