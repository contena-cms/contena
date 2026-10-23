<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * @implements Rule<New_>
 *
 * @internal
 */
class NoDelayStampRule implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        if (!$node->class instanceof Name) {
            return [];
        }

        if ($node->class->toString() !== DelayStamp::class) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Usage of DelayStamp is not allowed, as it is not compatible with all messenger transports.')
                ->identifier('contena.noDelayStamp')
                ->build(),
        ];
    }
}
