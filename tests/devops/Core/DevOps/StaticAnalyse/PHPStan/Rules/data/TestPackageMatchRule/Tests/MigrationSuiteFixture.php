<?php declare(strict_types=1);

namespace Contena\Tests\Migration\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TestPackageMatchRule\Covered\CheckoutService;

#[CoversClass(CheckoutService::class)]
class MigrationSuiteFixture extends TestCase
{
}
