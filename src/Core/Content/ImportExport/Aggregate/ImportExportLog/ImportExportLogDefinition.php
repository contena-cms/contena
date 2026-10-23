<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Contena\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileDefinition;
use Contena\Core\Content\ImportExport\ImportExportProfileDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @codeCoverageIgnore
 */
class ImportExportLogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'import_export_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ImportExportLogEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([
            new WriteProtection(Context::SYSTEM_SCOPE),
        ]);
    }

    protected function defineFields(): FieldCollection
    {
        $fields = [
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('activity', 'activity'))->addFlags(new Required()),
            (new StringField('state', 'state'))->addFlags(new Required()),
            (new IntField('records', 'records'))->addFlags(new Required()),
            new FkField('admin_user_id', 'admin_userId', AdminUserDefinition::class),
            new FkField('profile_id', 'profileId', ImportExportProfileDefinition::class),
            new FkField('file_id', 'fileId', ImportExportFileDefinition::class),
            new FkField('invalid_records_log_id', 'invalidRecordsLogId', ImportExportLogDefinition::class),
            new StringField('username', 'username'),
            new StringField('profile_name', 'profileName'),
            (new JsonField('config', 'config', [], []))->addFlags(new Required()),
            new JsonField('result', 'result', [], []),
            new ManyToOneAssociationField('admin_user', 'admin_user_id', AdminUserDefinition::class),
            new ManyToOneAssociationField('profile', 'profile_id', ImportExportProfileDefinition::class, 'id'),
            new OneToOneAssociationField('file', 'file_id', 'id', ImportExportFileDefinition::class, false),
            new OneToOneAssociationField('invalidRecordsLog', 'invalid_records_log_id', 'id', ImportExportLogDefinition::class, false),
            new OneToOneAssociationField('failedImportLog', 'id', 'invalid_records_log_id', ImportExportLogDefinition::class),
        ];

        return new FieldCollection($fields);
    }
}
