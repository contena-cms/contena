<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class ArraySetterClass
{
    /**
     * @var array<string, object>
     */
    private array $searchResults = [];

    public function addSearch(object $entityResult, string $entityName): void
    {
        $this->searchResults[$entityName] = $entityResult;
    }
}
