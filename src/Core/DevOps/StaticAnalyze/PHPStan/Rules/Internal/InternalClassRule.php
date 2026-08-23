<?php declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Internal;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\Migration\MigrationStep;
use Contena\Core\Framework\Plugin;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
class InternalClassRule implements Rule
{
    private const array INTERNAL_NAMESPACES = [
        '\\DevOps\\StaticAnalyze',
        '\\Core\\Maintenance',
    ];
    private const array SUBSCRIBER_EXCEPTIONS = [
        RefreshIndexCommand::class,
    ];
    private const array MESSAGE_HANDLER_EXCEPTIONS = [
        EntityIndexerRegistry::class,
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
        $doc = $node->getDocComment()?->getText() ?? '';

        if ($this->isInternal($doc)) {
            return [];
        }

        $class = $node->getClassReflection()->getName();

        if ($this->isExample($node)) {
            return [];
        }
        if ($this->isTestClass($node)) {
            return [
                RuleErrorBuilder::message(\sprintf(
                    'Test classes (%s) must be flagged @internal to not be captured by the BC checker',
                    $node->getClassReflection()->getName()
                ))
                    ->identifier('contena.internalClass')
                    ->build(),
            ];
        }

        if ($this->isBundle($node)) {
            return [
                RuleErrorBuilder::message('Bundles must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('contena.internalClass')
                    ->build(),
            ];
        }

        if ($this->isEventSubscriber($node) && !$this->isFinal($node->getClassReflection(), $doc) && !\in_array($class, self::SUBSCRIBER_EXCEPTIONS, true)) {
            return [
                RuleErrorBuilder::message('Event subscribers must be flagged @internal or @final to not be captured by the BC checker.')
                    ->identifier('contena.internalClass')
                    ->build(),
            ];
        }

        if ($namespace = $this->isInInternalNamespace($node)) {
            return [
                RuleErrorBuilder::message('Classes in `' . $namespace . '` namespace must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('contena.internalClass')
                    ->build(),
            ];
        }

        if ($this->isMigrationStep($node)) {
            return [
                RuleErrorBuilder::message('Migrations must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('contena.internalClass')
                    ->build(),
            ];
        }

        if ($this->isMessageHandler($node) && !\in_array($class, self::MESSAGE_HANDLER_EXCEPTIONS, true)) {
            return [
                RuleErrorBuilder::message('MessageHandlers must be flagged @internal to not be captured by the BC checker.')
                    ->identifier('contena.internalClass')
                    ->build(),
            ];
        }

        if ($this->isParentInternalAndAbstract($scope) && !$this->isFinal($node->getClassReflection(), $doc)) {
            return [
                RuleErrorBuilder::message('Classes that extend an @internal abstract class must be flagged @internal or @final to not be captured by the BC checker.')
                    ->identifier('contena.internalClass')
                    ->build(),
            ];
        }

        return [];
    }

    private function isTestClass(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        if (\str_contains($namespace, 'Contena\\Core\\Test\\Stub\\')) {
            return false;
        }

        if (\str_contains($namespace, '\\Test\\Integration\\Builder\\')) {
            // Test builder classes are not internal by design
            return false;
        }

        if (\str_contains($namespace, '\\Test\\')) {
            return true;
        }

        if (\str_contains($namespace, '\\Tests\\')) {
            return true;
        }

        if ($node->getClassReflection()->getParentClass() === null) {
            return false;
        }

        return $node->getClassReflection()->getParentClass()->getName() === TestCase::class;
    }

    private function isInternal(string $doc): bool
    {
        return \str_contains($doc, '@internal') || \str_contains($doc, 'reason:becomes-internal');
    }

    private function isBundle(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

        if ($class->isAnonymous()) {
            return false;
        }

        return $class->getParentClass()->getName() === Bundle::class && $class->getName() !== Plugin::class;
    }

    private function isEventSubscriber(InClassNode $node): bool
    {
        foreach ($node->getClassReflection()->getInterfaces() as $interface) {
            if ($interface->getName() === EventSubscriberInterface::class) {
                return true;
            }
        }

        return false;
    }

    private function isInInternalNamespace(InClassNode $node): ?string
    {
        $namespace = $node->getClassReflection()->getName();

        foreach (self::INTERNAL_NAMESPACES as $internalNamespace) {
            if (\str_contains($namespace, $internalNamespace)) {
                return $internalNamespace;
            }
        }

        return null;
    }

    private function isMigrationStep(InClassNode $node): bool
    {
        $class = $node->getClassReflection();

        if ($class->getParentClass() === null) {
            return false;
        }

        return $class->getParentClass()->getName() === MigrationStep::class;
    }

    private function isMessageHandler(InClassNode $node): bool
    {
        $class = $node->getClassReflection()->getNativeReflection();

        if ($class->isAbstract()) {
            // abstract base classes should not be final
            return false;
        }

        return $class->getAttributes(AsMessageHandler::class) !== [];
    }

    private function isFinal(ClassReflection $class, string $doc): bool
    {
        return str_contains($doc, '@final') || str_contains($doc, 'reason:becomes-final') || $class->isFinal();
    }

    private function isParentInternalAndAbstract(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        \assert($class !== null);
        $parent = $class->getParentClass();

        if ($parent === null) {
            return false;
        }

        if (!$parent->isAbstract()) {
            return false;
        }

        $native = $parent->getNativeReflection();

        $doc = $native->getDocComment() ?: '';

        return $this->isInternal($doc);
    }

    private function isExample(InClassNode $node): bool
    {
        $namespace = $node->getClassReflection()->getName();

        return \str_contains($namespace, 'Contena\\Tests\\Examples\\');
    }
}
