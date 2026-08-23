<?php declare(strict_types=1);

namespace Contena\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Contena\Core\Framework\Migration\MigrationCollectionLoader;
use Contena\Core\Framework\Migration\MigrationRuntime;
use Contena\Core\Framework\Migration\MigrationSource;

/**
 * @internal
 */
class MigrationCollectionFactory
{
    public function __construct(private readonly string $projectDir)
    {
    }

    public function getMigrationCollectionLoader(Connection $connection): MigrationCollectionLoader
    {
        $nullLogger = new NullLogger();

        return new MigrationCollectionLoader(
            $connection,
            new MigrationRuntime($connection, $nullLogger),
            $nullLogger,
            $this->collect(),
        );
    }

    /**
     * @return list<MigrationSource>
     */
    private function collect(): array
    {
        return [
            new MigrationSource('core', []),
            $this->createMigrationSource('V6_8'),
        ];
    }

    private function createMigrationSource(string $version): MigrationSource
    {
        if (\is_file($this->projectDir . '/platform/src/Core/schema.sql')) {
            $coreBasePath = $this->projectDir . '/platform/src/Core';
            $frontendBasePath = $this->projectDir . '/platform/src/Frontend';
            $adminBasePath = $this->projectDir . '/platform/src/Administration';
        } elseif (\is_file($this->projectDir . '/src/Core/schema.sql')) {
            $coreBasePath = $this->projectDir . '/src/Core';
            $frontendBasePath = $this->projectDir . '/src/Frontend';
            $adminBasePath = $this->projectDir . '/src/Administration';
        } elseif (\is_file($this->projectDir . '/vendor/contena/platform/src/Core/schema.sql')) {
            $coreBasePath = $this->projectDir . '/vendor/contena/platform/src/Core';
            $frontendBasePath = $this->projectDir . '/vendor/contena/platform/src/Frontend';
            $adminBasePath = $this->projectDir . '/vendor/contena/platform/src/Administration';
        } else {
            $coreBasePath = $this->projectDir . '/vendor/contena/core';
            $frontendBasePath = $this->projectDir . '/vendor/contena/frontend';
            $adminBasePath = $this->projectDir . '/vendor/contena/administration';
        }

        $hasFrontendMigrations = is_dir($frontendBasePath);
        $hasAdminMigrations = is_dir($adminBasePath);

        $source = new MigrationSource('core.' . $version, [
            \sprintf('%s/Migration/%s', $coreBasePath, $version) => \sprintf('Contena\\Core\\Migration\\%s', $version),
        ]);

        if ($hasFrontendMigrations) {
            $source->addDirectory(\sprintf('%s/Migration/%s', $frontendBasePath, $version), \sprintf('Contena\\Frontend\\Migration\\%s', $version));
        }

        if ($hasAdminMigrations) {
            $source->addDirectory(\sprintf('%s/Migration/%s', $adminBasePath, $version), \sprintf('Contena\\Administration\\Migration\\%s', $version));
        }

        return $source;
    }
}
