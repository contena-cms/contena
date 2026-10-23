<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ParameterMutationClass
{
    public function processCriteria(\ArrayObject $criteria, string $channelId): void
    {
        $criteria->append($channelId);
    }
}
