<?php declare(strict_types=1);

namespace Contena\Administration\DependencyInjection;

use Contena\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;

class AdministrationMigrationCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Administration';
    }
}
