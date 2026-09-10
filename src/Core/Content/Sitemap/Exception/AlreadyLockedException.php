<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Exception;

use Contena\Core\Content\Sitemap\SitemapException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
class AlreadyLockedException extends SitemapException
{
    public function __construct(ChannelContext $channelContext)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::SITEMAP_ALREADY_LOCKED,
            'Cannot acquire lock for channel {{ channelId }} and language {{ languageId }}',
            [
                'channelId' => $channelContext->getChannelId(),
                'languageId' => $channelContext->getLanguageId(),
            ],
        );
    }
}
