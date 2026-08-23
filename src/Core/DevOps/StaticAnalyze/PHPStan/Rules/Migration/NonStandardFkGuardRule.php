<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires new migrations to run raw ALTER/INDEX DDL through
 * {@see \Contena\Core\Framework\Migration\MigrationStep::executeDdlStatement()} so unknown schema
 * drift in platform or plugin tables cannot trigger MySQL bug #118151 during an upgrade.
 *
 * @internal
 *
 * @implements Rule<MethodCall>
 */
class NonStandardFkGuardRule implements Rule
{
    use InMigrationClassTrait;

    private const string CUTOFF_UNIX_TIMESTAMP = '2026-08-03 00:00:00';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toString() !== 'executeStatement') {
            return [];
        }

        if (!$this->isInMigrationClass($scope) || !$this->isRecentMigration($scope)) {
            return [];
        }

        $sql = $node->getArgs()[0]->value ?? null;
        if (!$sql instanceof String_ || !$this->isGuardedDdl($sql->value)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Raw ALTER TABLE or index DDL in a migration must go through '
                . 'MigrationStep::executeDdlStatement() to survive MySQL 8.4 non-standard '
                . 'foreign-key drift (MySQL bug #118151).'
            )
                ->identifier('contena.nonStandardFkGuard')
                ->build(),
        ];
    }

    private function isRecentMigration(Scope $scope): bool
    {
        $className = $scope->getClassReflection()?->getName() ?? '';
        $className = substr($className, (int) strrpos($className, '\\') + 1);

        if (preg_match('/Migration(\d{10})/', $className, $matches) !== 1) {
            return false;
        }

        return (int) $matches[1] > (int) strtotime(self::CUTOFF_UNIX_TIMESTAMP);
    }

    private function isGuardedDdl(string $sql): bool
    {
        foreach ([
            '/ALTER\s+TABLE\s+`?\w+`?/i',
            '/CREATE\s+(?:UNIQUE\s+|FULLTEXT\s+)?INDEX\s+\S+\s+ON\s+`?\w+`?/i',
            '/DROP\s+INDEX\s+\S+\s+ON\s+`?\w+`?/i',
        ] as $pattern) {
            if (preg_match($pattern, $sql) === 1) {
                return true;
            }
        }

        return false;
    }
}
