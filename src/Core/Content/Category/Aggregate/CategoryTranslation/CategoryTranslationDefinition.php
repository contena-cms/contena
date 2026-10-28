<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Aggregate\CategoryTranslation;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BreadcrumbField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CategoryTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'category_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CategoryTranslationEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CategoryTranslationCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return CategoryDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new BreadcrumbField())->addFlags(new ApiAware(), new WriteProtected()),
            new JsonField('slot_config', 'slotConfig'),
            (new StringField('link_type', 'linkType'))->addFlags(new ApiAware(), new Choice([
                CategoryDefinition::LINK_TYPE_CATEGORY,
                CategoryDefinition::LINK_TYPE_PRODUCT,
                CategoryDefinition::LINK_TYPE_EXTERNAL,
                CategoryDefinition::LINK_TYPE_LANDING_PAGE,
            ])),
            (new IdField('internal_link', 'internalLink'))->addFlags(new ApiAware()),
            (new StringField('external_link', 'externalLink'))->addFlags(new ApiAware()),
            (new BoolField('link_new_tab', 'linkNewTab'))->addFlags(new ApiAware()),
            (new LongTextField('description', 'description'))->addFlags(new ApiAware(), new AllowHtml()),
            (new StringField('meta_title', 'metaTitle'))->addFlags(new ApiAware()),
            (new StringField('meta_description', 'metaDescription'))->addFlags(new ApiAware()),
            (new StringField('keywords', 'keywords'))->addFlags(new ApiAware()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}
