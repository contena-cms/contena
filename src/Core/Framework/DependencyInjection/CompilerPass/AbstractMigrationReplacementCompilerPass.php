<?php

declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Migration\MigrationSource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

abstract class AbstractMigrationReplacementCompilerPass implements CompilerPassInterface
{
    private const array MAJOR_VERSIONS = ['V6_8'];

    public function process(ContainerBuilder $container): void
    {
        $migrationPath = $this->getMigrationPath();

        foreach (self::MAJOR_VERSIONS as $major) {
            $versionedMigrationPath = $migrationPath . '/Migration/' . $major;

            if (\is_dir($versionedMigrationPath)) {
                $migrationSource = $container->getDefinition(MigrationSource::class . '.core.' . $major);
                $migrationSource->addMethodCall('addDirectory', [$versionedMigrationPath, 'Contena\\' . $this->getMigrationNamespacePart() . '\Migration\\' . $major]);
            }
        }
    }

    abstract protected function getMigrationPath(): string;

    abstract protected function getMigrationNamespacePart(): string;
}
