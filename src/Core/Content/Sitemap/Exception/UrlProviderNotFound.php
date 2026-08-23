<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Exception;

use Contena\Core\Framework\HttpException;
use Symfony\Component\HttpFoundation\Response;

class UrlProviderNotFound extends HttpException
{
    public function __construct(string $provider)
    {
        parent::__construct(Response::HTTP_NOT_FOUND, 'CONTENT__SITEMAP_PROVIDER_NOT_FOUND', 'Provider "{{ provider }}" not found.', ['provider' => $provider]);
    }
}
