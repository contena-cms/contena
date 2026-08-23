<?php declare(strict_types=1);

namespace Contena\Frontend\Theme;

use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeChannelDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeChildDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeMediaDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeTranslationDefinition;

class ThemeDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'theme';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return ThemeCollection::class;
    }

    public function getEntityClass(): string
    {
        return ThemeEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new StringField('technical_name', 'technicalName')->addFlags(new ApiAware()),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new StringField('author', 'author')->addFlags(new ApiAware(), new Required()),
            new TranslatedField('description')->addFlags(new ApiAware()),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new FkField('preview_media_id', 'previewMediaId', MediaDefinition::class)->addFlags(new ApiAware()),
            new FkField('parent_theme_id', 'parentThemeId', self::class)->addFlags(new ApiAware()),
            new JsonField('theme_json', 'themeJson'),
            new JsonField('base_config', 'baseConfig')->addFlags(new ApiAware()),
            new JsonField('config_values', 'configValues')->addFlags(new ApiAware()),
            new BoolField('active', 'active')->addFlags(new ApiAware(), new Required()),

            new TranslationsAssociationField(ThemeTranslationDefinition::class, 'theme_id')->addFlags(new Required()),
            new ManyToManyAssociationField('media', MediaDefinition::class, ThemeMediaDefinition::class, 'theme_id', 'media_id')->addFlags(new ApiAware()),
            new ManyToOneAssociationField('previewMedia', 'preview_media_id', MediaDefinition::class),
            new ManyToManyAssociationField('dependentThemes', self::class, ThemeChildDefinition::class, 'parent_id', 'child_id'),
            new ManyToManyAssociationField('channels', ChannelDefinition::class, ThemeChannelDefinition::class, 'theme_id', 'channel_id'),
        ]);
    }
}
