<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Exception;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidTemplateException extends HttpException
{
    final public const ERROR_CODE = 'FRAMEWORK__INVALID_SEO_TEMPLATE';

    public function __construct(string $message)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, self::ERROR_CODE, $message);
    }
}
