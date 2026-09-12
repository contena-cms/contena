<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberGroupTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;

class MemberGroupTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'member_group_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MemberGroupTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return MemberGroupTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return MemberGroupDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new DataScopeField()->setDescription('Non-null identity of the owning data scope for member group translation.'),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new StringField('registration_title', 'registrationTitle')->addFlags(new ApiAware()),
            new LongTextField('registration_introduction', 'registrationIntroduction')->addFlags(new ApiAware(), new AllowHtml()),
            new LongTextField('registration_seo_meta_description', 'registrationSeoMetaDescription')->addFlags(new ApiAware()),
            new CustomFields()->addFlags(new ApiAware()),
        ]);
    }
}
