<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class InvalidChannelIdException extends ContenaHttpException
{
    public function __construct(string $channelId)
    {
        parent::__construct(
            'The provided channelId "{{ channelId }}" is invalid.',
            ['channelId' => $channelId]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__INVALID_CHANNEL';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
