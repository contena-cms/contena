<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Analyser\SpecifiedTypes;
use PHPStan\Analyser\TypeSpecifier;
use PHPStan\Analyser\TypeSpecifierAwareExtension;
use PHPStan\Analyser\TypeSpecifierContext;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\MethodTypeSpecifyingExtension;
use PHPStan\Type\NullType;
use PHPStan\Type\TypeCombinator;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * @internal
 */
class EntityExistenceHasEntityNameSpecifyingExtension implements MethodTypeSpecifyingExtension, TypeSpecifierAwareExtension
{
    private TypeSpecifier $typeSpecifier;

    public function getClass(): string
    {
        return EntityExistence::class;
    }

    public function isMethodSupported(MethodReflection $methodReflection, MethodCall $node, TypeSpecifierContext $context): bool
    {
        $declaringClass = $methodReflection->getDeclaringClass();

        return (
            $declaringClass->getName() === EntityExistence::class
            || $declaringClass->is(EntityExistence::class)
        )
            && $methodReflection->getName() === 'hasEntityName' && !$context->null();
    }

    public function specifyTypes(
        MethodReflection $methodReflection,
        MethodCall $node,
        Scope $scope,
        TypeSpecifierContext $context
    ): SpecifiedTypes {
        $getExpr = new MethodCall($node->var, 'getEntityName');

        $getterTypes = $this->typeSpecifier->create(
            $getExpr,
            TypeCombinator::removeNull($scope->getType($getExpr)),
            $context,
            $scope
        );

        return $getterTypes->unionWith(
            $this->typeSpecifier->create(
                $getExpr,
                new NullType(),
                $context->negate(),
                $scope
            )
        );
    }

    public function setTypeSpecifier(TypeSpecifier $typeSpecifier): void
    {
        $this->typeSpecifier = $typeSpecifier;
    }
}
