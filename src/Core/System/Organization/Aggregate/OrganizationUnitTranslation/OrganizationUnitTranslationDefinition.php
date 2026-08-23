<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationUnitTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;

class OrganizationUnitTranslationDefinition extends EntityTranslationDefinition
{
    final public const string ENTITY_NAME = 'organization_unit_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrganizationUnitTranslationCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrganizationUnitTranslationEntity::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function getParentDefinitionClass(): string
    {
        return OrganizationUnitDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned organization unit translation.'),
            new StringField('name', 'name')->addFlags(new ApiAware(), new Required()),
            new LongTextField('description', 'description')->addFlags(new ApiAware()),
        ]);
    }
}
