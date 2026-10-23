<?php declare(strict_types=1);

namespace Contena\Core\Content\MailTemplate\Exception;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.8.0 - Will be removed as it is not used anymore
 *
 * @codeCoverageIgnore
 */
class ChannelNotFoundException extends ContenaHttpException
{
    public function __construct(string $channelId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct(
            'Sales channel with id "{{ channelId }}" was not found.',
            ['channelId' => $channelId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__CHANNEL_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
