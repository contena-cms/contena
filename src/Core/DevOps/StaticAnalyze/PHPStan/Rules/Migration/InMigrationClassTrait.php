<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration;

use Contena\Core\Framework\Migration\MigrationStep;
use PHPStan\Analyser\Scope;

/**
 * @internal
 */
trait InMigrationClassTrait
{
    protected function isInMigrationClass(Scope $scope): bool
    {
        if (!$scope->isInClass()) {
            return false;
        }

        return $scope->getClassReflection()->is(MigrationStep::class);
    }
}
