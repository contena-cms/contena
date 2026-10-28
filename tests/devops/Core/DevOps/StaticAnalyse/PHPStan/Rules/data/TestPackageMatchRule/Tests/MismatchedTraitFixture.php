<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Tests;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
#[CoversTrait(CheckoutHelperTrait::class)]
class MismatchedTraitFixture extends TestCase
{
}
