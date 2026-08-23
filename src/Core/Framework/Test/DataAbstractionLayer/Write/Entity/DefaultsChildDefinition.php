<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Write\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class DefaultsChildDefinition extends EntityDefinition
{
    final public const string SCHEMA = 'CREATE TABLE IF NOT EXISTS  `defaults_child` (
        `id` BINARY(16) NOT NULL PRIMARY KEY,
        `defaults_id` BINARY(16) NOT NULL,
        `foo` text NOT NULL,
        `created_at` DATETIME NOT NULL
    )';

    public function getEntityName(): string
    {
        return 'defaults_child';
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey()),
            new FkField('defaults_id', 'defaultsId', DefaultsDefinition::class),
            new StringField('foo', 'foo')->addFlags(new Required()),
            new TranslatedField('name'),
            new TranslationsAssociationField(DefaultsChildTranslationDefinition::class, 'defaults_child_id'),
            new ManyToOneAssociationField('defaults', 'defaults_id', DefaultsDefinition::class),
        ]);
    }
}
