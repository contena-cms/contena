<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Includes the authenticated channel in HTTP cache keys, so pages of
 * different channels (and therefore different tenants)
 * never share a cache entry, even when the request URI matches.
 *
 * @internal
 */
final class ChannelCacheKeySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [HttpCacheKeyEvent::class => 'addChannel'];
    }

    public function addChannel(HttpCacheKeyEvent $event): void
    {
        if ($event->has('channel')) {
            return;
        }

        $accessKey = $event->request->headers->get(PlatformRequest::HEADER_ACCESS_KEY);
        if (!\is_string($accessKey) || $accessKey === '') {
            return;
        }

        try {
            $isChannelKey = AccessKeyHelper::getOrigin($accessKey) === 'channel';
        } catch (ApiException) {
            // Malformed access keys are rejected by the authentication layer.
            return;
        }

        if (!$isChannelKey) {
            return;
        }

        $channelId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `channel` WHERE `access_key` = :accessKey',
            ['accessKey' => $accessKey],
        );

        if (!\is_string($channelId)) {
            return;
        }

        $event->add('channel', $channelId);
    }
}
