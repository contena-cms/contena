<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class MailEventConfigurationException extends ContenaHttpException
{
    public function __construct(
        string $message,
        string $eventClass
    ) {
        parent::__construct(
            'Failed processing the mail event: {{ errorMessage }}. {{ eventClass }}',
            [
                'errorMessage' => $message,
                'eventClass' => $eventClass,
            ]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MAIL_INVALID_EVENT_CONFIGURATION';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
