<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Exception;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidSeoUrlException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, 'FRAMEWORK__INVALID_SEO_URL', $message);
    }
}
