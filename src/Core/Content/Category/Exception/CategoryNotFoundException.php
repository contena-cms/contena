<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CategoryNotFoundException extends ContenaHttpException
{
    public function __construct(string $categoryId)
    {
        parent::__construct(
            'Category "{{ categoryId }}" not found.',
            ['categoryId' => $categoryId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__CATEGORY_NOT_FOUND';
    }
}
