<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversTargetInCoverageSourceRule\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExcludedEntity::class)]
class CoversSuffixExcludedFixture extends TestCase
{
}
