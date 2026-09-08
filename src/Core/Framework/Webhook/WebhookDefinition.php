<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;

use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @codeCoverageIgnore
 */
class WebhookDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'webhook';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return WebhookEntity::class;
    }

    public function getCollectionClass(): string
    {
        return WebhookCollection::class;
    }

    public function since(): ?string
    {
        return '6.3.1.0';
    }

    public function getDefaults(): array
    {
        return [
            'onlyLiveVersion' => false,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name')->addFlags(new Required()),
            new StringField('event_name', 'eventName', 500)->addFlags(new Required()),
            new StringField('url', 'url', 500)->addFlags(new Required()),
            new BoolField('only_live_version', 'onlyLiveVersion'),
            new FkField('app_id', 'appId', AppDefinition::class),
            new ManyToOneAssociationField('app', 'app_id', AppDefinition::class),
        ]);

        return $collection;
    }
}
