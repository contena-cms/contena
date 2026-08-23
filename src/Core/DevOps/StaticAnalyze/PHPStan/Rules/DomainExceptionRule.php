<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use Contena\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Contena\Core\Framework\Adapter\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Contena\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyException;
use Contena\Core\Framework\Adapter\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Contena\Core\Framework\Framework;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\HttpException;
use Contena\Core\Kernel;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Console\Command\Command;

/**
 * @internal
 *
 * @implements Rule<Throw_>
 */
class DomainExceptionRule implements Rule
{
    use InTestClassTrait;

    /**
     * @var list<string>
     */
    private const array VALID_SUB_DOMAINS = [
        'Cart',
        'Payment',
        'Order',
    ];

    /**
     * @var list<string>
     */
    private const array EXCLUDED_NAMESPACES = [
        'Contena\Core\DevOps\StaticAnalyze\\',
    ];

    /**
     * @var array<string, string>
     */
    private const array REMAPPED_DOMAINS = [
        Kernel::class => FrameworkException::class,
        Framework::class => FrameworkException::class,
        VarnishReverseProxyGateway::class => ReverseProxyException::class,
        FastlyReverseProxyGateway::class => ReverseProxyException::class,
    ];

    /**
     * @var array<string, class-string<HttpException>>
     */
    private const array REMAPPED_NAMESPACE_DOMAINS = [];

    /**
     * @var array<string>
     */
    private array $validExceptionClasses;

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly Configuration $configuration,
    ) {
        // see src/Core/DevOps/StaticAnalyze/PHPStan/common.neon for the default config
        $this->validExceptionClasses = $this->configuration->getAllowedNonDomainExceptions();
    }

    public function getNodeType(): string
    {
        return Throw_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->isInTestClass($scope) || !$scope->isInClass()) {
            return [];
        }

        if (!$node instanceof Throw_) {
            return [];
        }

        if ($node->expr instanceof StaticCall) {
            return $this->validateDomainExceptionClass($node->expr, $scope);
        }

        if (!$node->expr instanceof New_) {
            return [];
        }

        $namespace = $scope->getNamespace();
        if (\is_string($namespace)) {
            foreach (self::EXCLUDED_NAMESPACES as $excludedNamespace) {
                if (\str_starts_with($namespace, $excludedNamespace)) {
                    return [];
                }
            }
        }

        \assert($node->expr->class instanceof Name);
        $exceptionClass = $node->expr->class->toString();

        if (\in_array($exceptionClass, $this->validExceptionClasses, true)) {
            return [];
        }

        // Allow InvalidArgumentException in commands to validate user input
        if ($scope->getClassReflection()->is(Command::class) && $exceptionClass === 'InvalidArgumentException') {
            return [];
        }

        return [
            RuleErrorBuilder::message('Throwing new exceptions within classes are not allowed. Please use domain exception pattern. See https://github.com/contena-cms/contena/blob/trunk/coding-guidelines/core/domain-exceptions.md')
                ->identifier('contena.domainException')
                ->build(),
        ];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateDomainExceptionClass(StaticCall $node, Scope $scope): array
    {
        \assert($node->class instanceof Name);
        $exceptionClass = $node->class->toString();

        if (!\str_starts_with($exceptionClass, 'Contena\\Core\\')) {
            return [];
        }

        $exception = $this->reflectionProvider->getClass($exceptionClass);
        if (!$exception->is(HttpException::class)) {
            return [
                RuleErrorBuilder::message(\sprintf('Domain exception class %s has to extend the \Contena\Core\Framework\HttpException class', $exceptionClass))
                    ->identifier('contena.domainException')
                    ->build(),
            ];
        }

        $reflection = $scope->getClassReflection();
        \assert($reflection !== null);
        if (!\str_starts_with($reflection->getName(), 'Contena\\Core\\')) {
            return [];
        }

        if ($this->isRemapped($reflection->getName(), $exceptionClass)) {
            return [];
        }

        $parts = \explode('\\', $reflection->getName());

        $domain = $parts[2] ?? '';
        $sub = $parts[3] ?? '';

        $acceptedClasses = [
            \sprintf('Contena\\Core\\%s\\%s\\%sException', $domain, $sub, $sub),
            \sprintf('Contena\\Core\\%s\\%sException', $domain, $domain),
        ];

        foreach ($acceptedClasses as $expected) {
            if ($exceptionClass === $expected || $exception->is($expected)) {
                return [];
            }
        }

        // Is it in a subdomain?
        if (isset($parts[5]) && \in_array($parts[4], self::VALID_SUB_DOMAINS, true)) {
            $expectedSub = \sprintf('\\%s\\%sException', $parts[4], $parts[4]);
            if (\str_starts_with(strrev($exceptionClass), strrev($expectedSub))) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(\sprintf('Expected domain exception class %s, got %s', $acceptedClasses[0], $exceptionClass))
                ->identifier('contena.domainException')
                ->build(),
        ];
    }

    private function isRemapped(string $source, string $exceptionClass): bool
    {
        foreach (self::REMAPPED_NAMESPACE_DOMAINS as $namespace => $mappedException) {
            if (\str_starts_with($source, $namespace)) {
                return $exceptionClass === $mappedException;
            }
        }

        if (!\array_key_exists($source, self::REMAPPED_DOMAINS)) {
            return false;
        }

        return self::REMAPPED_DOMAINS[$source] === $exceptionClass;
    }
}
