<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class DuplicateProductSortingKeyException extends ContenaHttpException
{
    public function __construct(
        string $key,
        \Throwable $e
    ) {
        parent::__construct(
            'Sorting with key "{{ key }}" already exists.',
            ['key' => $key],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_PRODUCT_SORTING_KEY';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
