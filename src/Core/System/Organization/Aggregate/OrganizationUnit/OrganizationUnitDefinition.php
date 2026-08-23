<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationUnit;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Organization\Aggregate\OrganizationUnitTranslation\OrganizationUnitTranslationDefinition;
use Contena\Core\System\Organization\OrganizationDefinition;

class OrganizationUnitDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'organization_unit';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrganizationUnitCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrganizationUnitEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'position' => 1,
            'active' => true,
        ];
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned organization unit.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the organization unit.'),
            new StringField('technical_name', 'technicalName', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Stable technical name of the organization unit.'),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('description')->addFlags(new ApiAware()),
            new IntField('position', 'position')->addFlags(new ApiAware())->setDescription('Numerical value that indicates the display order.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('Whether the organization unit is available for selection.'),
            new CustomFields()->addFlags(new ApiAware()),
            new OneToManyAssociationField('organizations', OrganizationDefinition::class, 'organization_unit_id')->addFlags(new ApiAware(), new RestrictDelete()),
            new TranslationsAssociationField(OrganizationUnitTranslationDefinition::class, 'organization_unit_id')->addFlags(new ApiAware(), new CascadeDelete(), new Required()),
        ]);
    }
}
