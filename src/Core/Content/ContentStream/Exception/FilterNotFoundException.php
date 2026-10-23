<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class FilterNotFoundException extends ContenaHttpException
{
    public function __construct(string $type)
    {
        parent::__construct('Filter for type {{ type}} not found', ['type' => $type]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__CONTENT_STREAM_FILTER_NOT_FOUND';
    }
}
