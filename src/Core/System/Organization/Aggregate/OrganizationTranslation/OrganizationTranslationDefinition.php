<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Organization\OrganizationDefinition;

class OrganizationTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'organization_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrganizationTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrganizationTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return OrganizationDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned organization translation.'),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new StringField('short_name', 'shortName', 100)->addFlags(new ApiAware()),
        ]);
    }
}
