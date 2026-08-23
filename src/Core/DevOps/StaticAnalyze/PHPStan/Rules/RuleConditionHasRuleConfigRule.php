<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Contena\Core\Framework\Rule\Container\AndRule;
use Contena\Core\Framework\Rule\Container\Container;
use Contena\Core\Framework\Rule\Container\FilterRule;
use Contena\Core\Framework\Rule\Container\NotRule;
use Contena\Core\Framework\Rule\Container\OrRule;
use Contena\Core\Framework\Rule\Container\XorRule;
use Contena\Core\Framework\Rule\DateRangeRule;
use Contena\Core\Framework\Rule\Rule as ContenaRule;
use Contena\Core\Framework\Rule\SimpleRule;
use Contena\Core\Framework\Rule\TimeRangeRule;
use Contena\Core\System\Member\Rule\MemberCustomFieldRule;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
class RuleConditionHasRuleConfigRule implements Rule
{
    /**
     * @var list<string>
     */
    private array $rulesAllowedToBeWithoutConfig = [
        FilterRule::class,
        Container::class,
        AndRule::class,
        NotRule::class,
        OrRule::class,
        XorRule::class,
        DateRangeRule::class,
        SimpleRule::class,
        TimeRangeRule::class,
        MemberCustomFieldRule::class,
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isRuleClass($scope) || $this->isAllowed($scope) || $this->isValid($scope)) {
            if ($this->isAllowed($scope) && $this->isValid($scope)) {
                return [
                    RuleErrorBuilder::message('This class is implementing the getConfig function and has a own admin component. Remove getConfig or the component.')
                        ->identifier('contena.ruleConfig')
                        ->build(),
                ];
            }

            return [];
        }

        return [
            RuleErrorBuilder::message('This class has to implement getConfig or implement a new admin component.')
                ->identifier('contena.ruleConfig')
                ->build(),
        ];
    }

    private function isValid(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null || !$class->hasMethod('getConfig')) {
            return false;
        }

        $declaringClass = $class->getMethod('getConfig', $scope)->getDeclaringClass();

        return $declaringClass->getName() !== ContenaRule::class;
    }

    private function isAllowed(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }

        return \in_array($class->getName(), $this->rulesAllowedToBeWithoutConfig, true);
    }

    private function isRuleClass(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }

        $namespace = $class->getName();
        if (!\str_contains($namespace, 'Contena\\Tests\\Unit\\') && !\str_contains($namespace, 'Contena\\Tests\\Migration\\')) {
            return false;
        }

        return $class->is(ContenaRule::class);
    }
}
