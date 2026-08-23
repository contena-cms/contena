<?php

declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;

/**
 * @implements Rule<String_>
 *
 * @internal
 */
class FrontendRouteUsageRule implements Rule
{
    /**
     * @var list<string>
     *
     * @phpstan-ignore contena.frontendRouteUsage, contena.frontendRouteUsage (As the PHPStan rule checks itself, this needs to be ignored)
     */
    private const array NOT_ALLOWED_FRONTEND_ROUTE_PREFIXES = ['frontend.', 'widgets.'];

    /**
     * @var list<string>
     */
    private array $allowedFrontendRouteNamespaces;

    public function __construct(
        private readonly Configuration $configuration,
    ) {
        // see src/Core/DevOps/StaticAnalyze/PHPStan/extension.neon for the default config
        $this->allowedFrontendRouteNamespaces = $this->configuration->getAllowedFrontendRouteNamespaces();
    }

    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $scopeClassReflection = $scope->getClassReflection();
        if (!$scopeClassReflection || TestRuleHelper::isTestClass($scopeClassReflection)) {
            return [];
        }

        $namespace = $scope->getNamespace();
        if ($namespace === null) {
            return [];
        }

        foreach ($this->allowedFrontendRouteNamespaces as $allowedFrontendRouteNamespace) {
            if (str_starts_with($namespace, $allowedFrontendRouteNamespace)) {
                return [];
            }
        }

        $value = $node->value;
        foreach (self::NOT_ALLOWED_FRONTEND_ROUTE_PREFIXES as $notAllowedFrontendRoutePrefix) {
            if (str_starts_with($value, $notAllowedFrontendRoutePrefix)) {
                $message = \sprintf(
                    'Using a route name starting with "%s" is not allowed in the "%s" namespace (found: "%s").',
                    $notAllowedFrontendRoutePrefix,
                    $namespace,
                    $value
                );

                return [
                    RuleErrorBuilder::message($message)
                        ->line($node->getStartLine())
                        ->identifier('contena.frontendRouteUsage')
                        ->tip(\sprintf('Routes starting with "%s" are provided by the Frontend package, which is not always installed.', $notAllowedFrontendRoutePrefix))
                        ->build(),
                ];
            }
        }

        return [];
    }
}
