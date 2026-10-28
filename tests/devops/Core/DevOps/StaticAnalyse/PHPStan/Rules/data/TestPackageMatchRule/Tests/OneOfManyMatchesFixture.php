<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;

#[Package('checkout')]
#[CoversClass(CheckoutService::class)]
#[CoversClass(FundamentalsFrameworkService::class)]
class OneOfManyMatchesFixture extends TestCase
{
}
