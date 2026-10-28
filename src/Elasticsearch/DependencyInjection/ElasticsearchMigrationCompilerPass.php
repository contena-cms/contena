<?php declare(strict_types=1);

namespace Contena\Elasticsearch\DependencyInjection;

use Contena\Core\Framework\DependencyInjection\CompilerPass\AbstractMigrationReplacementCompilerPass;
use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class ElasticsearchMigrationCompilerPass extends AbstractMigrationReplacementCompilerPass
{
    protected function getMigrationPath(): string
    {
        return \dirname(__DIR__);
    }

    protected function getMigrationNamespacePart(): string
    {
        return 'Elasticsearch';
    }
}
