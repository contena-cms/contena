<?php declare(strict_types=1);

namespace Contena\Administration\DependencyInjection;

use Contena\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;

#[BecomesInternal(version: 'v6.8.0')]
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
