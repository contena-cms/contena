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
#[Package('after-sales')]
class SalesChannelNotFoundException extends ContenaHttpException
{
    public function __construct(string $salesChannelId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        parent::__construct(
            'Sales channel with id "{{ salesChannelId }}" was not found.',
            ['salesChannelId' => $salesChannelId]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SALES_CHANNEL_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
