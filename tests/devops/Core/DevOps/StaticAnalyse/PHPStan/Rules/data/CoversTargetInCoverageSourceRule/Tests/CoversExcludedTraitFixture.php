<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversTargetInCoverageSourceRule\Tests;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversTrait(ToolingTrait::class)]
class CoversExcludedTraitFixture extends TestCase
{
}
