<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @implements Rule<New_>
 */
class NoNewRequestInFrontendRule implements Rule
{
    private const string CONTENA_FRONTEND_CONTROLLER = 'Contena\\Frontend\\Controller';

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        if ($node->class instanceof Name && $node->class->toString() === Request::class) {
            $classReflection = $scope->getClassReflection();
            if ($classReflection !== null && str_contains($classReflection->getName(), self::CONTENA_FRONTEND_CONTROLLER)) {
                return [
                    RuleErrorBuilder::message('Do not create new Request objects in frontend/controller namespace, because not all parameters might be available on the new request, leading to errors further down. Consider cloning the original request or use a different approach.')
                    ->identifier('contena.noNewRequestInFrontend')
                    ->build(),
                ];
            }
        }

        return [];
    }
}
