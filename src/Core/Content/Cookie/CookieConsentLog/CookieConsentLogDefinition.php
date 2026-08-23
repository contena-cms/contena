<?php declare(strict_types=1);

namespace Contena\Core\Content\Cookie\CookieConsentLog;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Language\LanguageDefinition;

/**
 * Anonymous audit trail of frontend cookie consent decisions (GDPR Recital 42).
 *
 * One row per visitor consent action. Contains no visitor identifiers by design;
 * it proves that consent was collected at a given time with a given banner
 * configuration (`config_hash` references `cookie_consent_config_version`),
 * not who gave it. The channel and language columns are intentionally
 * not enforced by foreign keys so evidence survives their deletion.
 */
class CookieConsentLogDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'cookie_consent_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CookieConsentLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CookieConsentLogCollection::class;
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned consent log.'),

            new FkField('channel_id', 'channelId', ChannelDefinition::class)->addFlags(new Required()),
            new FkField('language_id', 'languageId', LanguageDefinition::class)->addFlags(new Required()),

            new StringField('consent_action', 'consentAction', 32)->addFlags(new Required()),
            new ListField('accepted_groups', 'acceptedGroups', StringField::class)->addFlags(new Required()),
            new StringField('config_hash', 'configHash')->addFlags(new Required()),
        ]);
    }
}
