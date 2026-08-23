<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Contena\Core\Framework\HttpException;

/**
 * @implements Rule<ClassMethod>
 *
 * @internal
 */
class NoRuntimeExceptionInDomainExceptionsRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     *
     * @return array<int, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only care about static methods
        if (!$node instanceof ClassMethod || !$node->isStatic()) {
            return [];
        }

        // Not inside a class
        if (!$scope->isInClass()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        // Only classes that extend Contena\Core\Framework\HttpException
        if (!$classReflection->is(HttpException::class)) {
            return [];
        }

        // No declared return type -> nothing to check
        if ($node->returnType === null) {
            return [];
        }

        $method = $classReflection->getMethod($node->name->name, $scope);
        foreach ($method->getVariants() as $variant) {
            if ($variant->getReturnType()->isSuperTypeOf(new ObjectType(\RuntimeException::class))->yes()) {
                return [
                    RuleErrorBuilder::message(
                        \sprintf(
                            'Domain exception factory method %s::%s() might return \RuntimeException, however the ExceptionClass itself already extends \RuntimeException, therefore it should only return self.',
                            $classReflection->getName(),
                            $node->name,
                        )
                    )->identifier('contena.noRuntimeExceptionInDomainExceptions')->line($node->getStartLine())
                        ->build(),
                ];
            }
        }

        return [];
    }
}
