<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\UseCLIContextRule;

use Contena\Core\Framework\Context;

/**
 * @internal
 */
class NonRestrictedClass
{
    public function create(): void
    {
        Context::createDefaultContext();
    }
}
