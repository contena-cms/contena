<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CoversAttributeRule\Unit;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Struct\CloneTrait;

#[CoversTrait(CloneTrait::class)]
class CoversTraitFixture extends TestCase
{
}
