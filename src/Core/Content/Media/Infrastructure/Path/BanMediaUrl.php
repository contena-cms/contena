<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Infrastructure\Path;

use Contena\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Contena\Core\Content\Media\Core\Application\MediaReverseProxy;
use Contena\Core\Content\Media\Core\Params\UrlParams;
use Contena\Core\Content\Media\Core\Params\UrlParamsSource;
use Contena\Core\Content\Media\Event\MediaPathChangedEvent;

class BanMediaUrl
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MediaReverseProxy $gateway,
        private readonly AbstractMediaUrlGenerator $generator
    ) {
    }

    public function changed(MediaPathChangedEvent $event): void
    {
        if (!$this->gateway->enabled()) {
            return;
        }

        $params = [];
        foreach ($event->changed as $changed) {
            if (isset($changed['thumbnailId'])) {
                $params[] = new UrlParams(
                    id: $changed['thumbnailId'],
                    source: UrlParamsSource::THUMBNAIL,
                    path: $changed['path'],
                    mimeType: $changed['mimeType']
                );

                continue;
            }

            $params[] = new UrlParams(
                id: $changed['mediaId'],
                source: UrlParamsSource::MEDIA,
                path: $changed['path'],
                mimeType: $changed['mimeType']
            );
        }

        if ($params === []) {
            return;
        }

        $urls = $this->generator->generate($params);

        if ($urls === []) {
            return;
        }

        $this->gateway->ban($urls);
    }
}
