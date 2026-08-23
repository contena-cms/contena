<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Exception;

use Contena\Core\Framework\ContenaHttpException;
use Symfony\Component\HttpFoundation\Response;

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

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
