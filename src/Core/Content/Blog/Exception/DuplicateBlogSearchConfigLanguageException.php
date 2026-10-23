<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class DuplicateBlogSearchConfigLanguageException extends ContenaHttpException
{
    public function __construct(
        string $languageId,
        \Throwable $e
    ) {
        parent::__construct(
            'Blog search config with language_id {{ languageId }} already exists.',
            ['languageId' => $languageId],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__DUPLICATE_BLOG_SEARCH_CONFIG_LANGUAGE_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
