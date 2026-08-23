<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime;

use Contena\Core\Framework\Migration\MigrationRuntime;
use Contena\Core\Framework\Migration\MigrationSource;
use Contena\Core\Framework\Migration\MigrationStep;

/**
 * Fixture for MigrationRuntimeTest — the executable-migration lookup is a DB query,
 * so the list is injected here to unit-test the execution/error behaviour around it.
 *
 * @internal
 */
class TestableMigrationRuntime extends MigrationRuntime
{
    /**
     * @var list<string>
     */
    public array $executableMigrations = [];

    /**
     * @var list<string>
     */
    public array $executableDestructiveMigrations = [];

    public bool $storageEngineSet = false;

    public function getExecutableMigrations(MigrationSource $source, ?int $until = null, ?int $limit = null): array
    {
        /** @var array<class-string<MigrationStep>> */
        return $this->executableMigrations;
    }

    public function getExecutableDestructiveMigrations(MigrationSource $source, ?int $until = null, ?int $limit = null): array
    {
        /** @var array<class-string<MigrationStep>> */
        return $this->executableDestructiveMigrations;
    }

    protected function setDefaultStorageEngine(): void
    {
        $this->storageEngineSet = true;
    }
}
