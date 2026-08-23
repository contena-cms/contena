<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * @internal
 *
 * @implements Rule<MethodCall>
 */
class NoConstraintViolationGetMessageRule implements Rule
{
    private const string CONTENA_FRONTEND_CONTROLLER = 'Contena\\Frontend\\Controller';
    private const string MESSAGE = 'Do not use ConstraintViolationInterface::getMessage(). Use getCode() and translate it through the Contena translator.';

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->toString() !== 'getMessage') {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || !str_contains($classReflection->getName(), self::CONTENA_FRONTEND_CONTROLLER)) {
            return [];
        }

        $violationType = TypeCombinator::removeNull($scope->getType($node->var));

        if (!new ObjectType(ConstraintViolationInterface::class)->isSuperTypeOf($violationType)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('contena.constraintViolationGetMessage')
                ->build(),
        ];
    }
}
