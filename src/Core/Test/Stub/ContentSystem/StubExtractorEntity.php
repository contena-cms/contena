<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\ContentSystem;

use Contena\Core\Framework\DataAbstractionLayer\Entity;

/**
 * @final
 */
class StubExtractorEntity extends Entity
{
    public function __construct(string $id)
    {
        $this->setUniqueIdentifier($id);
    }

    public function getApiAlias(): string
    {
        return 'test_entity';
    }
}
