<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlTemplate;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;

class SeoUrlTemplateDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'seo_url_template';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return SeoUrlTemplateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SeoUrlTemplateCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Seo Url template.'),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of channel.'),

            new StringField('entity_name', 'entityName', 64)->addFlags(new Required())->setDescription('Name of the entity.'),
            new StringField('route_name', 'routeName')->addFlags(new Required())->setDescription('Name of the route.'),
            new StringField('template', 'template', 750)->addFlags(new AllowEmptyString())->setDescription('Template to generate an URL.'),
            new BoolField('is_valid', 'isValid')->addFlags(new ApiAware())->setDescription('Created SEO URL template can be made usable by setting `isValid` to true.'),
            new BoolField('is_headless', 'isHeadless')->addFlags(new ApiAware())->setDescription('Whether the template applies to headless (API type) channels. Derived from the route family.'),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class, 'id', false),
        ]);
    }
}
