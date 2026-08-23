<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Contena\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;

class FrontendMigrationReplacementCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Frontend';
    }
}
