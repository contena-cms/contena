<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Exception;

use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
class UnknownFileException extends ContenaHttpException
{
    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_UNKNOWN_FILE';
    }
}
