<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\MessagesShouldNotUsePHPStanTypes;

use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @phpstan-import-type PrimaryKeyList from AsyncMessageUsingPHPStanType
 */
class AsyncMessageImportingPHPStanType implements AsyncMessageInterface
{
    /**
     * @param PrimaryKeyList $primaryKeys
     */
    public function __construct(
        public readonly array $primaryKeys
    ) {
    }
}
