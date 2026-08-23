<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

class FrameworkMigrationReplacementCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__, 3);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Core';
    }
}
