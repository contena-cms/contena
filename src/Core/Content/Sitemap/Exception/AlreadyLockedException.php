<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Exception;

use Contena\Core\Framework\HttpException;
use Contena\Core\System\Channel\ChannelContext;
/**
 * @codeCoverageIgnore
 */
use Symfony\Component\HttpFoundation\Response;

class AlreadyLockedException extends HttpException
{
    public function __construct(ChannelContext $channelContext)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, 'CONTENT__SITEMAP_ALREADY_LOCKED', 'Cannot acquire lock for channel {{ channelId }} and language {{ languageId }}', [
            'channelId' => $channelContext->getChannelId(),
            'languageId' => $channelContext->getLanguageId(),
        ]);
    }
}
