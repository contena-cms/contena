<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Exception;

use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed, use {@see CmsException::pageNotFound} instead
 */
class PageNotFoundException extends ContenaHttpException
{
    final public const ERROR_CODE = 'CONTENT__CMS_PAGE_NOT_FOUND';

    public function __construct(string $pageId)
    {
        Feature::throwIfActive('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        parent::__construct(
            'Page with id "{{ pageId }}" was not found.',
            ['pageId' => $pageId]
        );
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }
}
