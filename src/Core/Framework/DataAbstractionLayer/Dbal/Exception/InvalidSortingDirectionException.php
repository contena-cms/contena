<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use DataAbstractionLayerException::invalidSortingDirection() instead
 */
#[Package('framework')]
class InvalidSortingDirectionException extends DataAbstractionLayerException
{
    public function __construct(string $direction)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__INVALID_SORT_DIRECTION',
            'The given sort direction "{{ direction }}" is invalid.',
            ['direction' => $direction]
        );
    }
}
