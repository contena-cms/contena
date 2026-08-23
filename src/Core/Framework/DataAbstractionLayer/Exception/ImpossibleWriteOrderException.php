<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Exception;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class ImpossibleWriteOrderException extends DataAbstractionLayerException
{
    public const string IMPOSSIBLE_WRITE_ORDER = 'FRAMEWORK__IMPOSSIBLE_WRITE_ORDER';

    /**
     * @param list<string> $remaining
     */
    public function __construct(array $remaining)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::IMPOSSIBLE_WRITE_ORDER,
            'Can not resolve write order for provided data. Remaining write order classes: {{ classesString }}',
            ['classes' => $remaining, 'classesString' => implode(', ', $remaining)]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__IMPOSSIBLE_WRITE_ORDER';
    }
}
