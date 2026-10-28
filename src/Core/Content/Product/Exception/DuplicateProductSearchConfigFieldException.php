<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Exception;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class DuplicateProductSearchConfigFieldException extends ContenaHttpException
{
    public function __construct(
        string $fieldName,
        \Throwable $e
    ) {
        parent::__construct(
            'Product search config with field {{ fieldName }} already exists.',
            ['fieldName' => $fieldName],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_PRODUCT_SEARCH_CONFIG_FIELD';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
