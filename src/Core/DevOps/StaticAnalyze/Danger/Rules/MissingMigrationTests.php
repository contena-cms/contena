<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;

/**
 * Every new migration class needs a test under `tests/migration/`.
 *
 * @internal
 */
class MissingMigrationTests
{
    private const array BUNDLES = [
        'Administration',
        'Core',
        'Elasticsearch',
        'Frontend',
    ];

    public function __invoke(Context $context): void
    {
        foreach (self::BUNDLES as $bundle) {
            $this->checkMigrationForBundle($bundle, $context);
        }
    }

    private function checkMigrationForBundle(string $bundle, Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        $migrationFiles = $files->filterStatus(File::STATUS_ADDED)->matches(\sprintf('src/%s/Migration/V*/Migration*.php', $bundle));
        $migrationTestFiles = $files->filterStatus(File::STATUS_ADDED)->matches(\sprintf('tests/migration/%s/V*/*.php', $bundle));

        if ($migrationFiles->count() && !$migrationTestFiles->count()) {
            $context->failure('Please add tests for your new Migration file');
        }
    }
}
