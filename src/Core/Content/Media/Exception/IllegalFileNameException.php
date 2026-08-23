<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Exception;

use Contena\Core\Content\Media\MediaException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class IllegalFileNameException extends MediaException
{
    public function __construct(string $filename, string $cause)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_ILLEGAL_FILE_NAME,
            'Provided filename "{{ fileName }}" is not permitted: {{ cause }}',
            ['fileName' => $filename, 'cause' => $cause]
        );
    }
}
