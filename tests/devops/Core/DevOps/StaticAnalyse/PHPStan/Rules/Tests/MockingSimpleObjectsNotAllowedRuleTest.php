<?php

declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\MockingSimpleObjectsNotAllowedRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<MockingSimpleObjectsNotAllowedRule>
 */
class MockingSimpleObjectsNotAllowedRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $contenaFixture = __DIR__ . '/../data/MockingSimpleObjects/ContenaBarFixture.php';
        $this->analyse([$contenaFixture], [
            [
                'Mocking of Contena\Core\System\Tag\TagEntity is not allowed. The object is very basic and can be constructed',
                16,
            ],
        ]);

        $commercialFixture = __DIR__ . '/../data/MockingSimpleObjects/CommercialBarFixture.php';
        $this->analyse([$commercialFixture], [
            [
                'Mocking of Contena\Core\System\Tag\TagEntity is not allowed. The object is very basic and can be constructed',
                16,
            ],
        ]);

        $parentFixture = __DIR__ . '/../data/MockingSimpleObjects/ParentBarFixture.php';
        $this->analyse([$parentFixture], [
            [
                'Mocking of Contena\Core\System\Tag\TagEntity is not allowed. The object is very basic and can be constructed',
                14,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new MockingSimpleObjectsNotAllowedRule(self::createReflectionProvider());
    }
}
