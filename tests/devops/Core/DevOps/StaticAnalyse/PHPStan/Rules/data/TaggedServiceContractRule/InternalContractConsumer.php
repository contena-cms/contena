<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\TaggedServiceContractRule;

class InternalContractConsumer
{
    /**
     * @param iterable<InternalContract> $services
     */
    public function __construct(iterable $services)
    {
    }
}
