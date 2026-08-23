<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Exception;

use Contena\Core\Framework\HttpException;

/**
 * @codeCoverageIgnore
 */
class UnknownFileException extends HttpException
{
    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_UNKNOWN_FILE';
    }
}
