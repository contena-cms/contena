<?php

declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Generator;

/**
 * This PHPStan rule prevents the manual creation of a `ChannelContext`.
 * It checks if the `ChannelContext` or any of its children are created manually.
 * Usually it should be sufficient to use the `ChannelContextFactory` or the `Generator::generateChannelContext` method.
 *
 * @internal
 *
 * @implements Rule<New_>
 */
class NoManualChannelContextCreationRule implements Rule
{
    /**
     * @var list<class-string>
     */
    private static array $allowedClassesWhichCanCreateChannelContext = [
        ChannelContextFactory::class,
        Generator::class,
    ];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        $class = $node->class;
        if (!$class instanceof Name) {
            return [];
        }

        $className = $class->toString();
        if (!$this->isChannelContextOrChild($className)) {
            return [];
        }

        $currentClass = $scope->getClassReflection();
        if ($currentClass && \in_array($currentClass->getName(), self::$allowedClassesWhichCanCreateChannelContext, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Manual creation of `Contena\Core\System\Channel\ChannelContext` is not allowed.')
                ->identifier('contena.noManualChannelContextCreation')
                ->addTip('Use `Contena\Core\System\Channel\Context\ChannelContextFactory` or `Contena\Core\Test\Generator::generateChannelContext` instead.')
                ->build(),
        ];
    }

    private function isChannelContextOrChild(string $className): bool
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $class = $this->reflectionProvider->getClass($className);
        if ($class->getName() === ChannelContext::class) {
            return true;
        }

        return $class->is(ChannelContext::class);
    }
}
