<?php declare(strict_types=1);

namespace Contena\Core\System\Locale\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidLocaleCodeException extends ContenaHttpException
{
    public function __construct(string $code)
    {
        parent::__construct('Cannot create or update locale with invalid code "{{ code }}"', ['code' => $code]);
    }

    public function getErrorCode(): string
    {
        return 'SYSTEM__INVALID_LOCALE_CODE';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
