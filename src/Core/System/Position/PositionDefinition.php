<?php declare(strict_types=1);

namespace Contena\Core\System\Position;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Position\Aggregate\PositionTranslation\PositionTranslationDefinition;
use Contena\Core\System\User\Aggregate\UserPosition\UserPositionDefinition;
use Contena\Core\System\User\UserDefinition;

class PositionDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'position';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return PositionCollection::class;
    }

    public function getEntityClass(): string
    {
        return PositionEntity::class;
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
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned position.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the position.'),
            new StringField('code', 'code', 64)->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Stable position code, unique within the owning tenant or platform.'),
            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('description')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new IntField('position', 'position')->addFlags(new ApiAware())->setDescription('Numerical value that indicates the display order.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('Whether the position is available for assignment.'),
            new CustomFields()->addFlags(new ApiAware()),
            new ManyToManyAssociationField('users', UserDefinition::class, UserPositionDefinition::class, 'position_id', 'user_id')->addFlags(new ApiAware()),
            new TranslationsAssociationField(PositionTranslationDefinition::class, 'position_id')->addFlags(new ApiAware(), new CascadeDelete(), new Required()),
        ]);
    }
}
