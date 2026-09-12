<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserDataScope;

use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeMembershipField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\User\UserDefinition;

/**
 * Stores an administration user's explicit grant in one data scope.
 *
 * This mapping is managed by the platform. Its data_scope_id is a grant
 * target, not the ownership marker of the mapping row itself.
 *
 * @internal
 */
class UserDataScopeDefinition extends MappingEntityDefinition
{
    final public const string ENTITY_NAME = 'user_data_scope';

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
            new FkField('data_scope_id', 'dataScopeId', 'data_scope')->addFlags(new PrimaryKey(), new Required()),
            new DataScopeMembershipField('user_id', 'userId', UserDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new BoolField('active', 'active')->setDescription('Whether the user may authenticate in this data scope.'),
            new BoolField('admin', 'admin')->setDescription('Whether the user is an administrator in this data scope.'),
            new BoolField('read_all_scopes', 'readAllScopes')->setDescription('Whether this platform grant permits reads across every data scope.'),
            new StringField('user_code', 'userCode')->setDescription('Optional user code in this data scope.'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            new ManyToOneAssociationField('dataScope', 'data_scope_id', 'data_scope', 'id', false),
        ]);
    }
}
