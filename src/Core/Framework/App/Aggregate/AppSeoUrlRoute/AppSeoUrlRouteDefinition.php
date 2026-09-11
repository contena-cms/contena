<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppSeoUrlRoute;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppSeoUrlRouteDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'app_seo_url_route';

    private const ROUTE_NAME_PREFIX = 'frontend.app.';

    public static function buildRouteName(string $appName, string $name): string
    {
        return self::ROUTE_NAME_PREFIX . $appName . '.' . $name;
    }

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return AppSeoUrlRouteCollection::class;
    }

    public function getEntityClass(): string
    {
        return AppSeoUrlRouteEntity::class;
    }

    public function since(): ?string
    {
        return '6.7.15.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AppDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new StringField('route_name', 'routeName'))->addFlags(new Required()),
            (new StringField('hook', 'hook'))->addFlags(new Required()),
            new StringField('entity_name', 'entityName', 64),
            new StringField('default_template', 'defaultTemplate', 750),
            new JsonField('paths', 'paths'),
            new JsonField('label', 'label'),
            (new FkField('app_id', 'appId', AppDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);
    }
}
