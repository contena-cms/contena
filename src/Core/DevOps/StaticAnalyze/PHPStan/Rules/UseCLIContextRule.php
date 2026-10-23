<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Console\Command\Command;

/**
 * @implements Rule<StaticCall>
 *
 * @internal
 */
class UseCLIContextRule implements Rule
{
    /**
     * @var list<class-string>
     */
    private array $baseClasses = [
        Command::class,
        ScheduledTaskHandler::class,
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier || $node->name->name !== 'createDefaultContext') {
            return [];
        }

        if (!$node->class instanceof Name || $node->class->toString() !== 'Contena\Core\Framework\Context') {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        foreach ($this->baseClasses as $baseClass) {
            if ($classReflection->is($baseClass)) {
                return [
                    RuleErrorBuilder::message('Method Context::createDefaultContext() should not be used in CLI context. Use Context::createCLIContext() instead.')
                        ->line($node->getStartLine())
                        ->identifier('contena.cliContext')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
