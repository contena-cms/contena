<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Exception;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class NoEntitiesForPreviewException extends HttpException
{
    final public const ERROR_CODE = 'FRAMEWORK__NO_ENTRIES_FOR_SEO_URL_PREVIEW';

    public function __construct(
        string $entityName,
        string $routeName
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::ERROR_CODE,
            'No entites of type {{ entityName }} could be found to create a preview for route {{ routeName }}',
            ['entityName' => $entityName, 'routeName' => $routeName]
        );
    }
}
