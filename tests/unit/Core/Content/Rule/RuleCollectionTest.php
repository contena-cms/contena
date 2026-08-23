<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(RuleCollection::class)]
class RuleCollectionTest extends TestCase
{
    public function testGetIdsByArea(): void
    {
        $ruleA = new RuleEntity();
        $ruleA->setId(Uuid::randomHex());
        $ruleA->setAreas(['a', 'b']);

        $ruleB = new RuleEntity();
        $ruleB->setId(Uuid::randomHex());
        $ruleB->setAreas(['b', 'c']);

        $ruleC = new RuleEntity();
        $ruleC->setId(Uuid::randomHex());
        $ruleC->setAreas(['c']);

        $ruleD = new RuleEntity();
        $ruleD->setId(Uuid::randomHex());

        $ruleE = new RuleEntity();
        $ruleE->setId(Uuid::randomHex());
        $ruleE->setAreas(['a', 'd']);

        $collection = new RuleCollection([$ruleA, $ruleB, $ruleC, $ruleD, $ruleE]);

        static::assertSame([
            'a' => [$ruleA->getId(), $ruleE->getId()],
            'b' => [$ruleA->getId(), $ruleB->getId()],
            'c' => [$ruleB->getId(), $ruleC->getId()],
            'd' => [$ruleE->getId()],
        ], $collection->getIdsByArea());
    }

    public function testGetIdsByAreaDeduplicatesAndKeepsInsertionOrder(): void
    {
        $ruleA = new RuleEntity();
        $ruleA->setId(Uuid::randomHex());
        $ruleA->setAreas(['a', 'a', 'b']);

        $ruleB = new RuleEntity();
        $ruleB->setId(Uuid::randomHex());
        $ruleB->setAreas(['a']);

        $collection = new RuleCollection([$ruleA, $ruleB]);

        $result = $collection->getIdsByArea();

        static::assertSame([
            'a' => [$ruleA->getId(), $ruleB->getId()],
            'b' => [$ruleA->getId()],
        ], $result);
        static::assertSame([0, 1], array_keys($result['a']));
    }
}
