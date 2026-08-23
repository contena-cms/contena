<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Aggregate\LandingPageTranslation;

use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class LandingPageTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'landing_page_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LandingPageTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return LandingPageTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return LandingPageDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new StringField('url', 'url')->addFlags(new ApiAware(), new Required()),
            new LongTextField('meta_title', 'metaTitle')->addFlags(new ApiAware()),
            new LongTextField('meta_description', 'metaDescription')->addFlags(new ApiAware()),
            new LongTextField('keywords', 'keywords')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
        ]);
    }
}
