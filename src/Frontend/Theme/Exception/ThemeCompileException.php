<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class ThemeCompileException extends ContenaHttpException
{
    public function __construct(
        string $themeName,
        string $message = '',
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'Unable to compile the theme "{{ themeName }}". {{ message }}',
            [
                'themeName' => $themeName,
                'message' => $message,
            ],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'THEME__COMPILING_ERROR';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
