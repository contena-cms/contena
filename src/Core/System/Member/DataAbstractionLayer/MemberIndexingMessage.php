<?php declare(strict_types=1);

namespace Contena\Core\System\Member\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;

class MemberIndexingMessage extends EntityIndexingMessage
{
    /**
     * @var string[]
     */
    private array $ids = [];

    /**
     * @return string[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    /**
     * @param array<string> $ids
     */
    public function setIds(array $ids): void
    {
        $this->ids = $ids;
    }
}
