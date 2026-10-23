<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Exception;

use Contena\Core\Framework\ContenaHttpException;

class InvalidSitemapKey extends ContenaHttpException
{
    public function __construct(string $sitemapKey)
    {
        parent::__construct('Invalid sitemap config key: "{{ sitemapKey }}"', ['sitemapKey' => $sitemapKey]);
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__SITEMAP_INVALID_KEY';
    }
}
