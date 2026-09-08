<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Template;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal only for use by the app-system
 */
class TemplateDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return TemplateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return TemplateCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of App template.'),
            new LongTextField('template', 'template')->addFlags(new Required(), new AllowHtml(false), new AllowEmptyString())->setDescription('Template for an app.'),
            new StringField('path', 'path', 1024)->addFlags(new Required())->setDescription('A relative URL to the app template.'),
            new BoolField('active', 'active')->addFlags(new Required())->setDescription('When boolean value is `true`, defined app templates are available for selection.'),
            new FkField('app_id', 'appId', AppDefinition::class)->addFlags(new Required())->setDescription('Unique identity of app.'),
            new StringField('hash', 'hash', 32),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
