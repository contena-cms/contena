<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogTranslation;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class BlogTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'blog_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isVersionAware(): bool
    {
        return true;
    }

    public function getCollectionClass(): string
    {
        return BlogTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return BlogDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new StringField('meta_description', 'metaDescription')->addFlags(new ApiAware()),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new LongTextField('keywords', 'keywords')->addFlags(new ApiAware()),
            new ListField('custom_search_keywords', 'customSearchKeywords', StringField::class)->addFlags(new ApiAware()),
            new LongTextField('description', 'description')->addFlags(new ApiAware(), new AllowHtml()),
            new StringField('description_teaser', 'descriptionTeaser', 512)->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Read-only, HTML-stripped excerpt of the description, derived on write.'),
            new StringField('meta_title', 'metaTitle')->addFlags(new ApiAware()),
            new StringField('og_title', 'ogTitle')->addFlags(new ApiAware()),
            new StringField('og_description', 'ogDescription')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
        ]);
    }
}
