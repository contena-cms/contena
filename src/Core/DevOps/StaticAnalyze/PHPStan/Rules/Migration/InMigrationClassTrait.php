<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration;

use PHPStan\Analyser\Scope;
use Contena\Core\Framework\Migration\MigrationStep;

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
