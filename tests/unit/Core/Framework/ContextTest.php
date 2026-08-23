<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Assert\Serialization;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(Context::class)]
class ContextTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $context = Context::createDefaultContext();

        static::assertInstanceOf(SystemSource::class, $context->getSource());
        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
        static::assertSame(Defaults::LIVE_VERSION, $context->getVersionId());
        static::assertFalse($context->hasGlobalTenantAccess());
        static::assertNull($context->getTenantId());
        static::assertTrue(Context::createGlobalContext()->hasGlobalTenantAccess());
        static::assertTrue(Context::createCLIContext()->hasGlobalTenantAccess());
        static::assertFalse(Context::createTenantContext('tenant-a')->hasGlobalTenantAccess());
    }

    public function testScope(): void
    {
        $context = Context::createDefaultContext();

        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());

        $context->scope('foo', static function (Context $context): void {
            static::assertSame('foo', $context->getScope());
        });

        static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
    }

    public function testScopeAddsTemporaryStatesAndRestoresThem(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $result = $context->scope(Context::SYSTEM_SCOPE, static function (Context $context): string {
            static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));

            return 'done';
        }, [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT]);

        static::assertSame('done', $result);
        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
    }

    public function testScopeKeepsExistingTemporaryState(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));
        $context->addState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT);

        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context): void {
            static::assertSame(Context::SYSTEM_SCOPE, $context->getScope());
            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
        }, [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT]);

        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
    }

    public function testExplicitSystemScopeInsideScopedStateSuppressesState(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id'));

        $context->scope(Context::SYSTEM_SCOPE, static function (Context $context): void {
            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));

            // This is expected: explicit system-scope opt-ins must not inherit temporary states from the surrounding scope.
            $context->scope(Context::SYSTEM_SCOPE, static function (Context $context): void {
                static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
            });

            static::assertTrue($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
        }, [Context::SYSTEM_SCOPE_DAL_WRITE_EVENT]);

        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
    }

    public function testVersionChange(): void
    {
        $versionId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $versionContext = $context->createWithVersionId($versionId);

        static::assertSame(Defaults::LIVE_VERSION, $context->getVersionId());
        static::assertSame($versionId, $versionContext->getVersionId());
    }

    public function testRuleIdsArePreservedAcrossVersionAndSerialization(): void
    {
        $context = new Context(new SystemSource(), ruleIds: ['rule-a', 'rule-b']);

        $versionContext = $context->createWithVersionId(Uuid::randomHex());
        $deserialized = Serialization::assertRoundTrip($context);

        static::assertSame(['rule-a', 'rule-b'], $versionContext->getRuleIds());
        static::assertSame(['rule-a', 'rule-b'], $deserialized->getRuleIds());
    }

    public function testTenantScopeIsPreservedAcrossVersionAndSerialization(): void
    {
        $tenantContext = new Context(new SystemSource(), tenantId: 'tenant-a');
        $globalContext = new Context(new SystemSource(), globalTenantAccess: true);

        $versionTenantContext = $tenantContext->createWithVersionId(Uuid::randomHex());
        $versionGlobalContext = $globalContext->createWithVersionId(Uuid::randomHex());
        $deserializedTenantContext = Serialization::assertRoundTrip($tenantContext);
        $deserializedGlobalContext = Serialization::assertRoundTrip($globalContext);

        static::assertSame('tenant-a', $versionTenantContext->getTenantId());
        static::assertSame('tenant-a', $deserializedTenantContext->getTenantId());
        static::assertTrue($versionGlobalContext->hasGlobalTenantAccess());
        static::assertTrue($deserializedGlobalContext->hasGlobalTenantAccess());
    }

    public function testRuleIdsCanBeChangedUntilTheyAreLocked(): void
    {
        $context = Context::createDefaultContext();
        $context->setRuleIds(['rule-a', '', 'rule-b']);

        static::assertSame(['rule-a', 'rule-b'], $context->getRuleIds());

        $context->lockRules();
        $this->expectExceptionObject(FrameworkException::contextRulesLocked());

        $context->setRuleIds(['rule-c']);
    }

    public function testVersionChangeInheritsExtensions(): void
    {
        $context = Context::createDefaultContext();
        $context->addExtension('foo', new ArrayEntity());

        static::assertNotNull($context->getExtension('foo'));

        $versionContext = $context->createWithVersionId(Uuid::randomHex());

        static::assertNotNull($versionContext->getExtension('foo'));
    }

    public function testExtensionsAreStripped(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);

        $normalizers = [new StructNormalizer(), new ObjectNormalizer($classMetadataFactory, null, null, null, $discriminator), new ArrayDenormalizer()];
        $serializer = new Serializer($normalizers, [new JsonEncoder()]);

        $context = Context::createDefaultContext();

        $context->addExtension('foo', new ArrayEntity());

        $serialized = $serializer->serialize($context, 'json');
        $deserialized = $serializer->deserialize($serialized, Context::class, 'json');

        static::assertInstanceOf(Context::class, $deserialized);

        static::assertEmpty($deserialized->getVars()['extensions']);
        static::assertEquals($context->getSource(), $deserialized->getSource());
        static::assertSame($context->getVersionId(), $deserialized->getVersionId());
        static::assertSame($context->getScope(), $deserialized->getScope());
        static::assertSame($context->getStates(), $deserialized->getStates());
        static::assertSame($context->getLanguageIdChain(), $deserialized->getLanguageIdChain());
        static::assertSame($context->considerInheritance(), $deserialized->considerInheritance());
    }

    public function testExtensionsAreStrippedOnNativeSerialize(): void
    {
        $context = Context::createDefaultContext();

        $context->addExtension('foo', new ArrayEntity());

        $deserialized = Serialization::assertRoundTrip($context);

        static::assertEmpty($deserialized->getVars()['extensions']);
        static::assertEquals($context->getSource(), $deserialized->getSource());
        static::assertSame($context->getVersionId(), $deserialized->getVersionId());
        static::assertSame($context->getScope(), $deserialized->getScope());
        static::assertSame($context->getStates(), $deserialized->getStates());
        static::assertSame($context->getLanguageIdChain(), $deserialized->getLanguageIdChain());
        static::assertSame($context->considerInheritance(), $deserialized->considerInheritance());
    }

    public static function twigMethodProviders(): \Generator
    {
        yield 'enableInheritance' => ['{{ context.enableInheritance("print_r") }}'];
        yield 'disableInheritance' => ['{{ context.disableInheritance("print_r") }}'];
        yield 'scope' => ['{{ context.scope("system", "print_r") }}'];
        yield 'tpl' => ['{{ context.enableInheritance("print_r") }}'];
    }

    #[DataProvider('twigMethodProviders')]
    public function testCallableCannotBeCalledFromTwig(string $tpl): void
    {
        $context = Context::createDefaultContext();

        $twig = new Environment(new ArrayLoader([
            'tpl' => $tpl,
        ]));

        $this->expectException(RuntimeError::class);

        $twig->render('tpl', ['context' => $context]);
    }
}
