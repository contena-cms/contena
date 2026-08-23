<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserConfig;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\User\UserDefinition;

class UserConfigDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'user_config';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return UserConfigEntity::class;
    }

    public function getCollectionClass(): string
    {
        return UserConfigCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.5.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return UserDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned user configuration.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of user configuration.'),
            new FkField('user_id', 'userId', UserDefinition::class)->addFlags(new Required())->setDescription('Unique identity of user.'),
            new StringField('key', 'key')->addFlags(new Required())->setDescription('Unique key for every userconfig.'),
            new JsonField('value', 'value')->setDescription('Value of the user configuration.'),

            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
        ]);
    }
}
