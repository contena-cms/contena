<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @codeCoverageIgnore
 */
class CmsPageDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'cms_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CmsPageEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CmsPageCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of CMS page.'),
            (new VersionField())->addFlags(new ApiAware()),
            (new TranslatedField('name'))->addFlags(new ApiAware()),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required())->setDescription('CMS page types can be `landingpage`, `page`, `blog_list`, `blog_detail`.'),
            (new StringField('entity', 'entity'))->addFlags(new ApiAware())->setDescription('This field will be implemented in the future.'),
            (new StringField('css_class', 'cssClass'))->addFlags(new ApiAware())->setDescription('One or more CSS classes added and separated by spaces.'),
            (new JsonField('config', 'config', [
                (new StringField('background_color', 'backgroundColor'))->addFlags(new ApiAware()),
            ]))->addFlags(new ApiAware())->setDescription('Background color of the CMS page.'),
            (new FkField('preview_media_id', 'previewMediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of media to be previewed.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            new LockedField(),

            (new OneToManyAssociationField('sections', CmsSectionDefinition::class, 'cms_page_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Content sections within the CMS page (layout blocks containing slots)'),
            (new TranslationsAssociationField(CmsPageTranslationDefinition::class, 'cms_page_id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('previewMedia', 'preview_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Preview image for the CMS page in admin panel and page selection'),

            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'cms_page_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('landingPages', LandingPageDefinition::class, 'cms_page_id'))->addFlags(new ApiAware(), new RestrictDelete())->setDescription('Landing pages using this CMS layout'),
            (new OneToManyAssociationField('homeChannels', ChannelDefinition::class, 'home_cms_page_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('blogs', BlogDefinition::class, 'cms_page_id'))->addFlags(new RestrictDelete()),
        ]);
    }
}
