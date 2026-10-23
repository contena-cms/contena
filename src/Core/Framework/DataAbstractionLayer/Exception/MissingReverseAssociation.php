<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\ContenaHttpException;

class MissingReverseAssociation extends ContenaHttpException
{
    public function __construct(
        string $source,
        string $target
    ) {
        parent::__construct(
            'Can not find reverse association in entity {{ source }} which should have an association to entity {{ target }}',
            ['source' => $source, 'target' => $target]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__MISSING_REVERSE_ASSOCIATION';
    }
}
