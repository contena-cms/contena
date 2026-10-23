<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Exception;

use Contena\Core\Framework\App\AppException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AppDownloadException extends AppException
{
    public const APP_DOWNLOAD_WRITE_FAILED = 'FRAMEWORK__APP_DOWNLOAD_WRITE_FAILED';
    public const APP_DOWNLOAD_TRANSPORT_ERROR = 'FRAMEWORK__APP_DOWNLOAD_TRANSPORT_ERROR';

    public static function cannotWrite(string $file, string $error): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_DOWNLOAD_WRITE_FAILED,
            'App archive could not be written to "{{ file }}". Error: "{{ error }}"."',
            ['file' => $file, 'error' => $error]
        );
    }

    public static function transportError(string $url, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_DOWNLOAD_TRANSPORT_ERROR,
            'App could not be downloaded from: "{{ url }}".',
            ['url' => $url],
            $previous
        );
    }
}
