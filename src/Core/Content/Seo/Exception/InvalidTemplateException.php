<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidTemplateException extends ContenaHttpException
{
    final public const ERROR_CODE = 'FRAMEWORK__INVALID_SEO_TEMPLATE';

    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
