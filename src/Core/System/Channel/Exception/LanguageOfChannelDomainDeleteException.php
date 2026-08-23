<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class LanguageOfChannelDomainDeleteException extends ContenaHttpException
{
    public function __construct(?\Throwable $e = null)
    {
        parent::__construct(
            'The language cannot be deleted because channel domains with this language exist.',
            [],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__LANGUAGE_OF_CHANNEL_DOMAIN_DELETE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
