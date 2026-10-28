<?php declare(strict_types=1);

namespace Contena\Storefront\DependencyInjection;

use Contena\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;

#[Package('discovery')]
#[BecomesInternal(version: 'v6.8.0')]
class StorefrontMigrationReplacementCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Storefront';
    }
}
