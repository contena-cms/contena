<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Entity;

use Contena\Core\Content\Blog\Aggregate\BlogContentLayout\BlogContentLayoutDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Contena\Core\Framework\ContentSystem\Layout\Field\ContentElementListField;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @final
 */
class ContentLayoutDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'content_layout';

    final public const string LAYOUT_FIELD = 'layout';

    final public const string ROOT_SOURCE_FIELD = 'root_source';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ContentLayoutEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ContentLayoutCollection::class;
    }

    public function since(): string
    {
        return '6.7.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned content layout.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            new StringField('name', 'name', 255)->addFlags(new ApiAware(), new Required()),
            new StringField('version', 'version', 20)->addFlags(new ApiAware(), new Required()),
            new ContentElementListField(self::LAYOUT_FIELD, self::LAYOUT_FIELD)->addFlags(new ApiAware(), new Required()),
            new StringField(self::ROOT_SOURCE_FIELD, 'rootSource')->addFlags(new ApiAware(), new Required(), new Immutable()),
            new OneToManyAssociationField('blogContentLayouts', BlogContentLayoutDefinition::class, 'content_layout_id', 'id')->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('categoryContentLayouts', CategoryContentLayoutDefinition::class, 'content_layout_id', 'id')->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('landingPageContentLayouts', LandingPageContentLayoutDefinition::class, 'content_layout_id', 'id')->addFlags(new RestrictDelete()),
        ]);
    }
}
