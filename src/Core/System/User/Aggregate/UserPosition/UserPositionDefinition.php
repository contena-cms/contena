<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserPosition;

use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Position\PositionDefinition;
use Contena\Core\System\User\UserDefinition;

class UserPositionDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'user_position';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned user position assignment.'),
            new FkField('user_id', 'userId', UserDefinition::class)->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new FkField('position_id', 'positionId', PositionDefinition::class)->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('position', 'position_id', PositionDefinition::class, 'id', false)->addFlags(new ApiAware()),
        ]);
    }
}
