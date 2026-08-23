<?php declare(strict_types=1);

namespace Contena\Core\System\Organization;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Organization\Aggregate\OrganizationTranslation\OrganizationTranslationDefinition;
use Contena\Core\System\Organization\Aggregate\OrganizationUnit\OrganizationUnitDefinition;

class OrganizationDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'organization';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrganizationCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrganizationEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'level' => 1,
            'childCount' => 0,
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
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned organization.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the organization.'),
            new ParentFkField(self::class)->addFlags(new ApiAware())->setDescription('Unique identity of the parent organization.'),
            new TreeLevelField('level', 'level')->addFlags(new ApiAware())->setDescription('Organization hierarchy level maintained by the DAL tree updater.'),
            new TreePathField('path', 'path')->addFlags(new ApiAware())->setDescription('Ancestor path maintained by the DAL tree updater.'),
            new ChildCountField()->addFlags(new ApiAware())->setDescription('Number of direct child organizations.'),
            new StringField('code', 'code', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Stable organization code, unique within the owning tenant or platform.'),
            new FkField('organization_unit_id', 'organizationUnitId', OrganizationUnitDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the organization unit.'),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('shortName')->addFlags(new ApiAware()),
            new IntField('position', 'position')->addFlags(new ApiAware())->setDescription('Numerical value that indicates the display order.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('Whether the organization is available for selection.'),
            new CustomFields()->addFlags(new ApiAware()),
            new ParentAssociationField(self::class, 'id')->addFlags(new ApiAware()),
            new ChildrenAssociationField(self::class)->addFlags(new ApiAware(), new CascadeDelete()),
            new ManyToOneAssociationField('organizationUnit', 'organization_unit_id', OrganizationUnitDefinition::class, 'id')->addFlags(new ApiAware()),
            new TranslationsAssociationField(OrganizationTranslationDefinition::class, 'organization_id')->addFlags(new ApiAware(), new Required()),
        ]);
    }
}
