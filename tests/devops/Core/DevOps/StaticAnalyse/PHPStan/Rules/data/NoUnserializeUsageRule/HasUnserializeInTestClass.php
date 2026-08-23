<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoUnserializeUsageRule;

use PHPUnit\Framework\TestCase;

class HasUnserializeInTestClass extends TestCase
{
    public function testSomething(string $serialized): mixed
    {
        return \unserialize($serialized);
    }

    public function testSomethingSneaky(string $serialized): mixed
    {
        /**
         * @phpstan-ignore contena.unserializeUsage
         */
        return \unserialize($serialized);
    }
}
