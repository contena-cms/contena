<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;

/**
 * @internal
 */
#[CoversClass(EntityNotExists::class)]
class EntityNotExistsTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            criteria: $criteria,
            primaryProperty: 'customerId',
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame($criteria, $entityNotExists->getCriteria());
        static::assertSame('customerId', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutCriteria(): void
    {
        $context = Context::createDefaultContext();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            primaryProperty: 'customerId',
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame('customerId', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutPrimaryProperty(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            criteria: $criteria,
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame($criteria, $entityNotExists->getCriteria());
        static::assertSame('id', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutPrimaryPropertyAndCriteria(): void
    {
        $context = Context::createDefaultContext();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame('id', $entityNotExists->getPrimaryProperty());
    }
}
