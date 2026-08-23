<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\CookieConsentConfigVersion;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Language\LanguageDefinition;

/**
 * Snapshot of the cookie banner configuration for a given configuration hash.
 *
 * Referenced by `cookie_consent_log.config_hash`, it preserves what the banner
 * looked like (groups, cookies, descriptions) when a consent was recorded.
 * New rows are only created when the banner configuration changes. The channel
 * and language columns are intentionally not enforced by foreign keys
 * so evidence survives their deletion.
 */
class CookieConsentConfigVersionDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'cookie_consent_config_version';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CookieConsentConfigVersionEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CookieConsentConfigVersionCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned consent configuration snapshot.'),

            new StringField('config_hash', 'configHash')->addFlags(new Required()),
            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new Required()),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new Required()),
            new JsonField('cookie_groups', 'cookieGroups')->addFlags(new Required()),
        ]);
    }
}
