<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(EntityExists::class)]
class EntityExistsTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityExists = new EntityExists(
            entity: 'blog_review',
            context: $context,
            primaryProperty: 'userId',
            criteria: $criteria,
            message: 'The {{ entity }} with {{ primaryProperty }} {{ id }} is unknown.',
        );

        static::assertSame('blog_review', $entityExists->getEntity());
        static::assertSame($context, $entityExists->getContext());
        static::assertSame($criteria, $entityExists->getCriteria());
        static::assertSame('userId', $entityExists->getPrimaryProperty());
        static::assertSame('The {{ entity }} with {{ primaryProperty }} {{ id }} is unknown.', $entityExists->getMessage());
    }

    public function testConstructorUsesDefaultsForCriteriaPrimaryPropertyAndMessage(): void
    {
        $context = Context::createDefaultContext();

        $entityExists = new EntityExists(
            entity: 'blog_review',
            context: $context,
        );

        static::assertSame('blog_review', $entityExists->getEntity());
        static::assertSame($context, $entityExists->getContext());
        static::assertEquals(new Criteria(), $entityExists->getCriteria());
        static::assertSame('id', $entityExists->getPrimaryProperty());
        static::assertSame('The {{ entity }} entity with {{ primaryProperty }} {{ id }} does not exist.', $entityExists->getMessage());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithoutEntity(): void
    {
        $this->expectExceptionObject(FrameworkException::missingOptions(\sprintf(
            'Option "entity" must be given for constraint %s',
            EntityExists::class
        )));

        new EntityExists(
            context: Context::createDefaultContext(),
            primaryProperty: 'userId',
            criteria: new Criteria(),
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithoutContext(): void
    {
        $this->expectExceptionObject(FrameworkException::missingOptions(\sprintf(
            'Option "context" must be given for constraint %s',
            EntityExists::class
        )));

        new EntityExists(
            entity: 'blog_review',
            primaryProperty: 'userId',
            criteria: new Criteria(),
        );
    }

    /**
     * Ignore deprecation triggered by Symfony as the parent constructor is called
     */
    #[IgnoreDeprecations]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithOptions(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityExists = new EntityExists([
            'entity' => 'blog_review',
            'context' => $context,
            'criteria' => $criteria,
            'primaryProperty' => 'userId',
        ]);

        static::assertSame('blog_review', $entityExists->getEntity());
        static::assertSame($context, $entityExists->getContext());
        static::assertSame($criteria, $entityExists->getCriteria());
        static::assertSame('userId', $entityExists->getPrimaryProperty());
    }

    /**
     * @param array{
     *     entity?: string|int,
     *     context?: Context,
     *     criteria?: Criteria|string,
     *     primaryProperty?: string|int
     * } $options
     */
    #[DataProvider('invalidOptionsProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConstructorWithInvalidOptions(array $options, FrameworkException $expectedException): void
    {
        $this->expectExceptionObject($expectedException);

        /** @phpstan-ignore argument.type (for test purpose) */
        new EntityExists($options);
    }

    /**
     * @return \Generator<string, array{array{
     *     entity?: string|int,
     *     context?: Context,
     *     criteria?: Criteria|string,
     *     primaryProperty?: string|int
     * }, FrameworkException}>
     */
    public static function invalidOptionsProvider(): \Generator
    {
        yield 'without entity' => [
            ['context' => Context::createDefaultContext(), 'criteria' => new Criteria(), 'primaryProperty' => 'userId'],
            FrameworkException::missingOptions(\sprintf('Option "entity" must be given for constraint %s', EntityExists::class)),
        ];

        yield 'with non string entity' => [
            ['entity' => 123, 'context' => Context::createDefaultContext()],
            FrameworkException::missingOptions(\sprintf('Option "entity" must be given for constraint %s', EntityExists::class)),
        ];

        yield 'without context' => [
            ['entity' => 'blog_review', 'criteria' => new Criteria(), 'primaryProperty' => 'userId'],
            FrameworkException::missingOptions(\sprintf('Option "context" must be given for constraint %s', EntityExists::class)),
        ];

        yield 'with invalid criteria' => [
            ['entity' => 'blog_review', 'context' => Context::createDefaultContext(), 'criteria' => 'invalid'],
            FrameworkException::invalidOptions(\sprintf('Option "criteria" must be an instance of %s for constraint %s', Criteria::class, EntityExists::class)),
        ];

        yield 'with invalid primary property' => [
            ['entity' => 'blog_review', 'context' => Context::createDefaultContext(), 'primaryProperty' => 123],
            FrameworkException::invalidOptions(\sprintf('Option "primaryProperty" must be a string for constraint %s', EntityExists::class)),
        ];
    }
}
