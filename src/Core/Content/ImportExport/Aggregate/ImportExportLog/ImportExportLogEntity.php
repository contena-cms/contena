<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Aggregate\ImportExportLog;

use Contena\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Contena\Core\Content\ImportExport\ImportExportProfileEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @codeCoverageIgnore
 */
class ImportExportLogEntity extends Entity
{
    use EntityIdTrait;

    final public const ACTIVITY_IMPORT = 'import';
    final public const ACTIVITY_EXPORT = 'export';
    final public const ACTIVITY_DRYRUN = 'dryrun';
    final public const ACTIVITY_INVALID_RECORDS_EXPORT = 'invalid_records_export';

    final public const ACTIVITY_TEMPLATE = 'template';

    protected string $activity;

    protected string $state;

    protected int $records = 0;

    protected ?string $username = null;

    protected ?string $profileName = null;

    protected ?AdminUserEntity $admin_user = null;

    protected ?string $admin_userId = null;

    protected ?ImportExportProfileEntity $profile = null;

    protected ?string $profileId = null;

    protected ?ImportExportFileEntity $file = null;

    protected ?string $fileId = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * @var array<string, mixed>
     */
    protected array $result = [];

    protected ?string $invalidRecordsLogId = null;

    protected ?self $invalidRecordsLog = null;

    protected ?self $failedImportLog = null;

    public function getActivity(): string
    {
        return $this->activity;
    }

    public function setActivity(string $activity): void
    {
        $this->activity = $activity;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getRecords(): int
    {
        return $this->records;
    }

    public function setRecords(int $records): void
    {
        $this->records = $records;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getProfileName(): ?string
    {
        return $this->profileName;
    }

    public function setProfileName(string $profileName): void
    {
        $this->profileName = $profileName;
    }

    public function getUser(): ?AdminUserEntity
    {
        return $this->admin_user;
    }

    public function setUser(AdminUserEntity $admin_userEntity): void
    {
        $this->admin_user = $admin_userEntity;
    }

    public function getUserId(): ?string
    {
        return $this->admin_userId;
    }

    public function setUserId(string $admin_userId): void
    {
        $this->admin_userId = $admin_userId;
    }

    public function getProfile(): ?ImportExportProfileEntity
    {
        return $this->profile;
    }

    public function setProfile(ImportExportProfileEntity $profile): void
    {
        $this->profile = $profile;
    }

    public function getProfileId(): ?string
    {
        return $this->profileId;
    }

    public function setProfileId(string $profileId): void
    {
        $this->profileId = $profileId;
    }

    public function getFile(): ?ImportExportFileEntity
    {
        return $this->file;
    }

    public function setFile(ImportExportFileEntity $file): void
    {
        $this->file = $file;
    }

    public function getFileId(): ?string
    {
        return $this->fileId;
    }

    public function setFileId(string $fileId): void
    {
        $this->fileId = $fileId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @param array<string, mixed> $result
     */
    public function setResult(array $result): void
    {
        $this->result = $result;
    }

    public function getInvalidRecordsLogId(): ?string
    {
        return $this->invalidRecordsLogId;
    }

    public function setInvalidRecordsLogId(?string $invalidRecordsLogId): void
    {
        $this->invalidRecordsLogId = $invalidRecordsLogId;
    }

    public function getInvalidRecordsLog(): ?ImportExportLogEntity
    {
        return $this->invalidRecordsLog;
    }

    public function setInvalidRecordsLog(?ImportExportLogEntity $invalidRecordsLog): void
    {
        $this->invalidRecordsLog = $invalidRecordsLog;
    }

    public function getFailedImportLog(): ?ImportExportLogEntity
    {
        return $this->failedImportLog;
    }

    public function setFailedImportLog(?ImportExportLogEntity $failedImportLog): void
    {
        $this->failedImportLog = $failedImportLog;
    }
}
