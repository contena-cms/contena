<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MessagesShouldNotUsePHPStanTypes;

use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

class AsyncMessageNotUsingPHPStanType implements AsyncMessageInterface
{
    public function __construct(
        public readonly array $primaryKeys
    ) {
    }
}
