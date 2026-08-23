<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Exception;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class CategoryNotFoundException extends HttpException
{
    public function __construct(string $categoryId)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, 'CONTENT__CATEGORY_NOT_FOUND', 'Category "{{ categoryId }}" not found.', ['categoryId' => $categoryId]);
    }
}
