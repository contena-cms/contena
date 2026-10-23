<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule;

use Contena\Core\Framework\Deprecation\BCChange\ClassMoved;

#[ClassMoved(version: 'v6.8.0', previousClassName: 'Contena\Tests\Legacy\UnregisteredClass')]
class ClassMovedAttributeUsage
{
}
